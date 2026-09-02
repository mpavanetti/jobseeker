<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Content-addressed artifact storage for the ML platform. Model files and
 * dataset snapshots are stored once, keyed by their SHA-256; run/model/dataset
 * rows only hold the digest and a `storage_uri`.
 *
 * `LocalMlArtifactStore` writes under a repository volume and is the default.
 * `S3MlArtifactStore` is the seam for the Kubernetes production path (any
 * S3-compatible endpoint incl. MinIO); its body is a stub.
 *
 * URIs are of the form `local://<sha-prefix>/<sha256><ext>` or
 * `s3://<bucket>/<key>` - opaque outside the store that produced them.
 */
abstract class MlArtifactStore
{
    /** @return string backend id: local|s3 */
    abstract public function name();

    /** @return bool backend usable right now */
    abstract public function healthy();

    /**
     * Persist a local temp file. Returns
     * {ok, sha256, size_bytes, media_type, uri, message}.
     */
    abstract public function putFile($sourcePath, $mediaType = 'application/octet-stream', $originalName = NULL);

    /** Persist an in-memory string (small JSON blobs, plots). Same return shape. */
    abstract public function putString($bytes, $mediaType = 'application/octet-stream', $originalName = NULL);

    /** @return string|false absolute readable path (may be a temp copy) or FALSE */
    abstract public function localPath($uri);

    abstract public function exists($uri);

    abstract public function delete($uri);

    protected function guessMediaType($name, $fallback = 'application/octet-stream')
    {
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        $map = array(
            'json' => 'application/json', 'csv' => 'text/csv', 'txt' => 'text/plain',
            'parquet' => 'application/vnd.apache.parquet', 'pkl' => 'application/octet-stream',
            'joblib' => 'application/octet-stream', 'pt' => 'application/octet-stream',
            'onnx' => 'application/octet-stream', 'png' => 'image/png', 'html' => 'text/html',
            'yaml' => 'application/yaml', 'yml' => 'application/yaml',
        );
        return isset($map[$ext]) ? $map[$ext] : $fallback;
    }
}

class MlArtifactStoreFactory
{
    public static function make($config = array())
    {
        $name = strtolower(trim((string) (isset($config['store']) ? $config['store']
            : (getenv('JOBSEEKER_ML_ARTIFACT_STORE') ?: 'local'))));
        if ($name === 's3' || $name === 'minio') {
            return new S3MlArtifactStore($config);
        }
        return new LocalMlArtifactStore($config);
    }
}

class LocalMlArtifactStore extends MlArtifactStore
{
    /** @var string */
    private $root;

    public function __construct($config = array())
    {
        $root = isset($config['root']) && trim((string) $config['root']) !== ''
            ? trim((string) $config['root'])
            : (getenv('JOBSEEKER_ML_ARTIFACT_ROOT') ?: (rtrim(FCPATH, '/\\').'/repository/ml/artifacts'));
        $this->root = rtrim($root, '/\\');
    }

    public function name()
    {
        return 'local';
    }

    public function healthy()
    {
        return $this->ensureDir($this->root);
    }

    public function putFile($sourcePath, $mediaType = 'application/octet-stream', $originalName = NULL)
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            return array('ok' => FALSE, 'message' => 'Source file is not readable.');
        }
        $sha = hash_file('sha256', $sourcePath);
        if ($sha === FALSE) {
            return array('ok' => FALSE, 'message' => 'Could not hash the source file.');
        }
        $ext = $originalName ? strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) : '';
        $relative = substr($sha, 0, 2).'/'.$sha.($ext !== '' ? '.'.$ext : '');
        $target = $this->root.'/'.$relative;
        if (! $this->ensureDir(dirname($target))) {
            return array('ok' => FALSE, 'message' => 'Could not create the artifact directory.');
        }
        if (! is_file($target) && ! @copy($sourcePath, $target)) {
            return array('ok' => FALSE, 'message' => 'Could not write the artifact.');
        }
        return array(
            'ok' => TRUE,
            'sha256' => $sha,
            'size_bytes' => (int) filesize($target),
            'media_type' => $mediaType ?: $this->guessMediaType($originalName),
            'uri' => 'local://'.$relative,
            'message' => 'stored',
        );
    }

    public function putString($bytes, $mediaType = 'application/octet-stream', $originalName = NULL)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'jsmlart');
        if ($tmp === FALSE || @file_put_contents($tmp, (string) $bytes) === FALSE) {
            return array('ok' => FALSE, 'message' => 'Could not buffer the artifact.');
        }
        $result = $this->putFile($tmp, $mediaType, $originalName);
        @unlink($tmp);
        return $result;
    }

    public function localPath($uri)
    {
        if (strpos((string) $uri, 'local://') !== 0) {
            return FALSE;
        }
        $relative = substr($uri, strlen('local://'));
        if ($relative === '' || strpos($relative, '..') !== FALSE) {
            return FALSE;
        }
        $path = $this->root.'/'.$relative;
        return is_file($path) && is_readable($path) ? $path : FALSE;
    }

    public function exists($uri)
    {
        return $this->localPath($uri) !== FALSE;
    }

    public function delete($uri)
    {
        $path = $this->localPath($uri);
        if ($path !== FALSE) {
            @unlink($path);
        }
        return TRUE;
    }

    private function ensureDir($dir)
    {
        return is_dir($dir) || @mkdir($dir, 0775, TRUE) || is_dir($dir);
    }
}

/**
 * S3 / MinIO backend seam. Intentionally a stub: signing SigV4 without ext-curl
 * is non-trivial, and the production target is expected to run behind an
 * IAM-role / service-account credential chain. Implement putFile/putString as a
 * PUT to {endpoint}/{bucket}/{sha-prefix}/{sha} and localPath as a GET to a
 * temp file. Config: JOBSEEKER_ML_S3_ENDPOINT, _BUCKET, _REGION, and the
 * standard AWS_* credential env already wired into the stack.
 */
class S3MlArtifactStore extends MlArtifactStore
{
    public function __construct($config = array()) {}

    private function stub()
    {
        return array('ok' => FALSE, 'message' =>
            'S3MlArtifactStore is a stub. Set JOBSEEKER_ML_ARTIFACT_STORE=local or implement the S3 path.');
    }

    public function name() { return 's3'; }
    public function healthy() { return FALSE; }
    public function putFile($sourcePath, $mediaType = 'application/octet-stream', $originalName = NULL) { return $this->stub(); }
    public function putString($bytes, $mediaType = 'application/octet-stream', $originalName = NULL) { return $this->stub(); }
    public function localPath($uri) { return FALSE; }
    public function exists($uri) { return FALSE; }
    public function delete($uri) { return FALSE; }
}
