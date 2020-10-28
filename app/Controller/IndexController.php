<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\Config\Annotation\Value;

class IndexController extends AbstractController
{
    /**
     * 后台地址
     *
     * @Value("app.admin_url")
     * @var string
     */
    protected $adminUrl;

    public function index()
    {
        return $this->response->redirect($this->adminUrl);
    }
}
