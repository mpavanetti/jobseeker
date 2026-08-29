<?php defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Session extends CI_Session
{
    private $jobseekerNoticeKey = '__jobseeker_scoped_notices';
    private $jobseekerConsumedNotices = array();

    private function noticeScope()
    {
        $CI =& get_instance();
        if (isset($CI->router)) {
            $scope = strtolower(trim((string) $CI->router->fetch_class()));
            if ($scope !== '') {
                return $scope;
            }
        }

        return 'application';
    }

    private function isNoticeKey($key)
    {
        return $key === 'error' || $key === 'success';
    }

    private function cleanNotices($notices)
    {
        $notices = is_array($notices) ? $notices : array();
        $cutoff = time() - 600;

        foreach ($notices as $scope => $scopeNotices) {
            if (! is_array($scopeNotices)) {
                unset($notices[$scope]);
                continue;
            }

            foreach ($scopeNotices as $key => $notice) {
                if (! is_array($notice) || ! isset($notice['created_at']) || (int) $notice['created_at'] < $cutoff) {
                    unset($notices[$scope][$key]);
                }
            }

            if (empty($notices[$scope])) {
                unset($notices[$scope]);
            }
        }

        return $notices;
    }

    private function storeNotice($key, $value)
    {
        $notices = $this->cleanNotices($this->userdata($this->jobseekerNoticeKey));
        $scope = $this->noticeScope();
        if (! isset($notices[$scope])) {
            $notices[$scope] = array();
        }
        $notices[$scope][$key] = array(
            'value' => $value,
            'created_at' => time()
        );
        $this->set_userdata($this->jobseekerNoticeKey, $notices);
        unset($this->jobseekerConsumedNotices[$scope][$key]);
    }

    private function consumeNotice($key)
    {
        $scope = $this->noticeScope();
        if (isset($this->jobseekerConsumedNotices[$scope]) && array_key_exists($key, $this->jobseekerConsumedNotices[$scope])) {
            return $this->jobseekerConsumedNotices[$scope][$key];
        }

        $notices = $this->cleanNotices($this->userdata($this->jobseekerNoticeKey));
        if (! isset($notices[$scope][$key])) {
            return NULL;
        }

        $value = $notices[$scope][$key]['value'];
        unset($notices[$scope][$key]);
        if (empty($notices[$scope])) {
            unset($notices[$scope]);
        }

        if (empty($notices)) {
            $this->unset_userdata($this->jobseekerNoticeKey);
        } else {
            $this->set_userdata($this->jobseekerNoticeKey, $notices);
        }

        if (! isset($this->jobseekerConsumedNotices[$scope])) {
            $this->jobseekerConsumedNotices[$scope] = array();
        }
        $this->jobseekerConsumedNotices[$scope][$key] = $value;
        return $value;
    }

    public function set_flashdata($data, $value = NULL)
    {
        if (is_string($data) && $this->isNoticeKey($data)) {
            $this->storeNotice($data, $value);
            return;
        }

        if (is_array($data)) {
            $regularFlash = array();
            foreach ($data as $key => $item) {
                if ($this->isNoticeKey($key)) {
                    $this->storeNotice($key, $item);
                } else {
                    $regularFlash[$key] = $item;
                }
            }
            if (empty($regularFlash)) {
                return;
            }
            return parent::set_flashdata($regularFlash);
        }

        return parent::set_flashdata($data, $value);
    }

    public function flashdata($key = NULL)
    {
        if (is_string($key) && $this->isNoticeKey($key)) {
            return $this->consumeNotice($key);
        }

        return parent::flashdata($key);
    }
}
