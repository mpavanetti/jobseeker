<?php if(!defined('BASEPATH') && !defined('JOBSEEKER_GIT_BRANCH_POLICY_TEST')) exit('No direct script access allowed');

/** Environment-aware defaults for Git-backed jobs and promotions. */
class GitBranchPolicy
{
    /**
     * Accept the remote URL forms supported by both Jenkins and OpenVSCode.
     * Credentials, query strings, and fragments are deliberately rejected so
     * secrets cannot be persisted in a project or leak into a build log.
     */
    public function cleanRepositoryUrl($repositoryUrl)
    {
        $repositoryUrl = trim((string) $repositoryUrl);
        if ($repositoryUrl === '' || strlen($repositoryUrl) > 1000 || preg_match('/[\x00-\x1F\x7F]/', $repositoryUrl)) {
            return FALSE;
        }

        if (filter_var($repositoryUrl, FILTER_VALIDATE_URL) !== FALSE) {
            $parts = parse_url($repositoryUrl);
            $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
            if (! in_array($scheme, array('http', 'https', 'ssh'), TRUE) || empty($parts['host'])
                || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
                || (in_array($scheme, array('http', 'https'), TRUE) && isset($parts['user']))) {
                return FALSE;
            }
            return $repositoryUrl;
        }

        if (preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9._\-]+:[A-Za-z0-9._\-\/]+(?:\.git)?$/', $repositoryUrl)) {
            return $repositoryUrl;
        }

        return FALSE;
    }

    /**
     * Configure with JOBSEEKER_GIT_ENVIRONMENT_BRANCHES, for example:
     * DEV=develop,QA=main,UAT=main,PROD=main,DEFAULT=main
     */
    public function mapping()
    {
        $mapping = array('DEV' => 'develop', 'DEFAULT' => 'main');
        $configured = getenv('JOBSEEKER_GIT_ENVIRONMENT_BRANCHES');
        foreach (preg_split('/[,;\r\n]+/', $configured === FALSE ? '' : (string) $configured) as $pair) {
            if (strpos($pair, '=') === FALSE) {
                continue;
            }
            list($environment, $branch) = array_map('trim', explode('=', $pair, 2));
            $environment = strtoupper($environment);
            $branch = $this->cleanBranch($branch);
            if (($environment === 'DEFAULT' || preg_match('/^[A-Z][A-Z0-9_-]{0,31}$/', $environment)) && $branch !== FALSE && $branch !== '') {
                $mapping[$environment] = $branch;
            }
        }
        return $mapping;
    }

    public function forEnvironment($environment)
    {
        $mapping = $this->mapping();
        $environment = strtoupper(trim((string) $environment));
        return isset($mapping[$environment]) ? $mapping[$environment] : $mapping['DEFAULT'];
    }

    /** Matches Git's ref-name restrictions used by Job Creation. */
    public function cleanBranch($branch)
    {
        $branch = trim((string) $branch);
        if ($branch === '') {
            return '';
        }
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,199}$/', $branch)
            || strpos($branch, '..') !== FALSE || strpos($branch, '//') !== FALSE
            || strpos($branch, '@{') !== FALSE || substr($branch, -5) === '.lock'
            || substr($branch, -1) === '/' || substr($branch, -1) === '.') {
            return FALSE;
        }
        return $branch;
    }
}
