<?php declare(strict_types=1);

namespace SwagVueStorefront\VueStorefront\Entity\SalesChannelNavigation;

use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use SwagVueStorefront\VueStorefront\Controller\PageController;
use SwagVueStorefront\VueStorefront\Entity\SalesChannelRoute\SalesChannelRouteEntity;
use SwagVueStorefront\VueStorefront\Entity\SalesChannelRoute\SalesChannelRouteRepository;

class SalesChannelNavigationRepository
{
    /**
     * @var NavigationLoader
     */
    private $navigationLoader;

    /**
     * @var SalesChannelRouteRepository
     */
    private $routeRepository;

    public function __construct(NavigationLoader $navigationLoader, SalesChannelRouteRepository $routeRepository)
    {
        $this->navigationLoader = $navigationLoader;
        $this->routeRepository = $routeRepository;
    }

    public function loadNavigation(string $rootId, int $depth, SalesChannelContext $context): SalesChannelNavigationEntity
    {
        $context->getSalesChannel()->setNavigationCategoryId($rootId);

        $navigation = $this->navigationLoader->load($rootId, $context, $rootId);

        return $this->createSalesChannelNavigation(
            $context->getContext(),
            new TreeItem(
                $navigation->getActive(),
                $navigation->getTree()
            ),
            $depth
        );
    }

    private function createSalesChannelNavigation(Context $context, TreeItem $treeItem, int $depth = PHP_INT_MAX, $currentLevel = 0): SalesChannelNavigationEntity
    {
        $navigationEntity = new SalesChannelNavigationEntity();

        $navigationEntity->setId($treeItem->getCategory()->getId());
        $navigationEntity->setName($treeItem->getCategory()->getName());
        $navigationEntity->setLevel($currentLevel);
        $navigationEntity->setCount(count($treeItem->getChildren()));

        /** @var SalesChannelRouteEntity $route */
        $route = $this->getSeoRoute(
            $context,
            PageController::NAVIGATION_PAGE_ROUTE,
            $navigationEntity->getId()
        );

        $navigationEntity->setRoute(
            [
                'path' => $route->getSeoPathInfo(),
                'resourceType' => PageController::NAVIGATION_PAGE_ROUTE
            ]
        );

        if($currentLevel == $depth) {
            return $navigationEntity;
        }

        $children = [];
        $currentLevel++;

        foreach($treeItem->getChildren() as $child)
        {
            /** @var $child TreeItem */
            $children[] = $this->createSalesChannelNavigation($context, $child, $depth, $currentLevel);
        }

        $navigationEntity->setChildren($children);

        return $navigationEntity;
    }

    private function getSeoRoute(Context $context, string $name, string $resourceIdentifier): SalesChannelRouteEntity
    {

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('foreignKey', $resourceIdentifier)
        );

        $routes = $this->routeRepository->search($criteria, $context);

        if(count($routes) >= 1)
        {
            return $routes[0];
        }

        $route = new SalesChannelRouteEntity();

        $route->setPathInfo('/');
        $route->setSeoPathInfo('/');
        $route->setResourceIdentifier($resourceIdentifier);
        $route->setRouteName($name);
        $route->setResource($name);

        return $route;
    }
}