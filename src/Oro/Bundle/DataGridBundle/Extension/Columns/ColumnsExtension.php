<?php

namespace Oro\Bundle\DataGridBundle\Extension\Columns;

use Doctrine\Bundle\DoctrineBundle\Registry;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Common\MetadataObject;
use Oro\Bundle\DataGridBundle\Entity\Repository\GridViewRepository;
use Oro\Bundle\DataGridBundle\Extension\AbstractExtension;
use Oro\Bundle\DataGridBundle\Extension\GridViews\View;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Oro\Bundle\UserBundle\Entity\User;

class ColumnsExtension extends AbstractExtension
{
    /** @var Registry */
    protected $registry;

    /** @var SecurityFacade */
    protected $securityFacade;

    /** @var AclHelper */
    protected $aclHelper;

    /**
     * @param Registry       $registry
     * @param SecurityFacade $securityFacade
     * @param AclHelper      $aclHelper
     */
    public function __construct(
        Registry $registry,
        SecurityFacade $securityFacade,
        AclHelper $aclHelper
    ) {
        $this->registry       = $registry;
        $this->securityFacade = $securityFacade;
        $this->aclHelper      = $aclHelper;
    }

    /**
     * {@inheritDoc}
     */
    public function isApplicable(DatagridConfiguration $config)
    {
        $columns = $config->offsetGetOr(Configuration::COLUMNS_PATH, []);
        $this->processConfigs($config);

        return count($columns) > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function processConfigs(DatagridConfiguration $config)
    {
        $this->validateConfiguration(
            new Configuration(),
            ['columns' => $config->offsetGetByPath(Configuration::COLUMNS_PATH)]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function visitMetadata(DatagridConfiguration $config, MetadataObject $data)
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            return;
        }

        $gridName  = $config->getName();
        $gridViews = $this->getGridViewRepository()->findGridViews($this->aclHelper, $currentUser, $gridName);

        if (!$gridViews) {
            return;
        }

        $newGridView  = new View('__all__');
        $columns      = $config->offsetGet('columns');
        $columnsOrder = $this->buildColumnsOrder($config->offsetGet('columns'));
        $columns      = $this->applyColumnsOrder($columns, $columnsOrder);

        $newGridView->setColumnsData($columns);
        $data->offsetAddToArray('initialState', ['columns' => $newGridView->getColumnsData()]);

        $columnsData  = [];
        $currentState = $data->offsetGet('state');

        foreach ($gridViews as $gridView) {
            if ((int)$currentState['gridView'] === $gridView->getId()) {
                $columnsData = $gridView->getColumnsData();
            }
        }

        if (count($columnsData) > 0) {
            $data->offsetAddToArray('state', ['columns' => $columnsData]);
        } else {
            $data->offsetAddToArray('state', ['columns' => $newGridView->getColumnsData()]);
        }
    }

    /**
     * @param array $columns
     *
     * @return array
     */
    protected function buildColumnsOrder(array $columns = [])
    {
        $orders = [];

        foreach ($columns as $name => $column) {
            if (array_key_exists('order', $column)) {
                $orders[$name] = (int)$column['order'];
            } else {
                $orders[$name] = 0;
            }
        }

        $iteration  = 1;
        $ignoreList = [];

        foreach ($orders as $name => &$order) {
            $iteration = $this->getFirstFreeOrder($iteration, $ignoreList);

            if (0 === $order) {
                $order = $iteration;
                $iteration++;
            } else {
                array_push($ignoreList, $order);
            }
        }

        unset($order);

        return $orders;
    }

    /**
     * @param array $columns
     * @param array $columnsOrder
     *
     * @return array
     */
    protected function applyColumnsOrder(array $columns, array $columnsOrder)
    {
        foreach ($columns as $name => &$column) {
            if (array_key_exists($name, $columnsOrder)) {
                $column['order'] = $columnsOrder[$name];
            }
        }

        unset($column);

        return $columns;
    }

    /**
     * Get first number which is not in ignore list
     *
     * @param int   $iteration
     * @param array $ignoreList
     *
     * @return int
     */
    protected function getFirstFreeOrder($iteration, array $ignoreList = [])
    {
        if (in_array($iteration, $ignoreList, true)) {
            ++$iteration;

            return $this->getFirstFreeOrder($iteration, $ignoreList);
        }

        return $iteration;
    }

    /**
     * @return User
     */
    protected function getCurrentUser()
    {
        $user = $this->securityFacade->getLoggedUser();
        if ($user instanceof User) {
            return $user;
        }

        return null;
    }

    /**
     * @return GridViewRepository
     */
    protected function getGridViewRepository()
    {
        return $this->registry->getRepository('OroDataGridBundle:GridView');
    }
}
