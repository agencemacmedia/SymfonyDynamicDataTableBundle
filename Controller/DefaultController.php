<?php

namespace AMM\SymfonyDynamicDataTableBundle\Controller;

use OC\PlatformBundle\Entity\Advert;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('AMMSymfonyDynamicDataTableBundle:Default:index.html.twig');
    }
}
