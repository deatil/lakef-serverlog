<?php

declare(strict_types=1);

namespace App\Admin\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Hyperf\Utils\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\View\RenderInterface;

class Permission implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var RenderInterface
     */
    protected $view;

    public function __construct(
        ContainerInterface $container, 
        ConfigInterface $config,
        RenderInterface $view,
        RequestInterface $request,
        ResponseInterface $response
    ) {
        $this->container = $container;
        $this->config = $config;
        $this->view = $view;
        $this->request = $request;
        $this->response = $response;
    }

    public function process(
        ServerRequestInterface $request, 
        RequestHandlerInterface $handler
    ): PsrResponseInterface 
    {
        if (! $this->shouldPassThrough($this->request)) {
            // 当前登陆账号信息
            $info = $this->request->getAttribute('authAdmin');
            if (empty($info) || $info['status'] != 1) {
                return $this->view->render('serverlog::no-permission', [
                    'message' => '账号不存在或者已被锁定',
                ]);
            }
            
            if ($info['id'] != $this->config->get('serverlog.passport.super_id')) {
                $uri = $this->request->path();
                $method = $this->request->getMethod();
                
                $permission = strtoupper($method).':/'.$uri;
                if (! $info->can($permission)) {
                    return $this->view->render('serverlog::no-permission', [
                        'message' => '权限被限制',
                    ]);
                }
            }
        }
        
        return $handler->handle($request);
    }
    
    protected function shouldPassThrough($request)
    {
        $excepts = array_merge($this->config->get('serverlog.auth.permission_excepts', []), [
            ltrim(admin_url('passport/captcha'), '/'),
            ltrim(admin_url('passport/login'), '/'),
            ltrim(admin_url('passport/logout'), '/'),
        ]);
        
        return $request->is($excepts);
    }
    
}
