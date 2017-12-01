<?php
error_reporting(E_ALL);
ini_set('display_errors', 'on');
class douniu extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 已登录授权调整到静态页面
     */
    public function index()
    {
        
    }

    /**
     * 获取用户信息
     */
    public function user_info()
    {
        //$mid = $this->session->userdata('mid');
        $mid = $this->input->get('mid');
        try {
            if ($mid <= 0) {
                throw new Exception("未登录", 500);
            }
            $userInfo = $this->getMember($mid);
            if (empty($userInfo)) {
                throw new Exception("未登录", 500);
            }
            $token = md5($mid . "token123#");
            $userInfo = array_merge($userInfo, ['token' => $token]);
            echo json_encode([
                'code' => 200,
                'message' => 'successful',
                'data' => $userInfo,
                ]);
        } catch (Exception $ex) {
            echo json_encode([
                'code' => $ex->getCode(),
                'message' => $ex->getMessage(),
                'data' => null
            ]);
        }
    }

    private function getMember($mid)
    {
        $this->db->select("mid,nickname,headimgurl");
        $this->db->where('mid', $mid);
        return $this->db->get('member')->row_array();
    }
}