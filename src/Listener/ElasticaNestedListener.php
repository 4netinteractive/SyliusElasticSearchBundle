<?php

namespace Lakion\SyliusElasticSearchBundle\Listener;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use FOS\ElasticaBundle\Provider\IndexableInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductTaxon;
use Sylius\Component\Core\Model\ProductVariantInterface;
use FOS\ElasticaBundle\Persister\ObjectPersister;
use Doctrine\Common\EventSubscriber;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Created by PhpStorm.
 * User: psihius
 * Date: 27.06.2017
 * Time: 22:43
 */
class ElasticaNestedListener implements EventSubscriber
{

    /**
     * Objects scheduled for insertion.
     *
     * @var array
     */
    public $scheduledForInsertion = [];

    /**
     * Objects scheduled to be updated or removed.
     *
     * @var array
     */
    public $scheduledForUpdate = [];

    /**
     * IDs of objects scheduled for removal.
     *
     * @var array
     */
    public $scheduledForDeletion = [];

    /**
     * Object persister.
     *
     * @var ObjectPersister
     */
    protected $objectPersister;

    /**
     * PropertyAccessor instance.
     *
     * @var PropertyAccessorInterface
     */
    protected $propertyAccessor;

    /**
     * Configuration for the listener.
     *
     * @var array
     */
    private $config;

    /**
     * @var IndexableInterface
     */
    private $indexable;

    /**
     * Constructor.
     *
     * @param ObjectPersister    $objectPersister
     * @param IndexableInterface $indexable
     * @param array              $config
     * @param LoggerInterface    $logger
     */
    public function __construct(
        ObjectPersister $objectPersister,
        IndexableInterface $indexable,
        array $config = [],
        LoggerInterface $logger = null
    ) {
        $this->config           = array_merge(
            [
                'identifier' => 'id',
            ],
            $config
        );
        $this->indexable        = $indexable;
        $this->objectPersister  = $objectPersister;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();

        if ($logger && $this->objectPersister instanceof ObjectPersister) {
            $this->objectPersister->setLogger($logger);
        }
    }

    public function getSubscribedEvents()
    {
        return [
            'postPersist',
            'preRemove',
            'postUpdate',
            'postFlush',
        ];
    }

    /**
     * Looks for objects being updated that should be indexed or removed from the index.
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function postUpdate(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();

        if ($entity instanceof ProductVariantInterface) {
            $entity = $entity->getProduct();
        } elseif ($entity instanceof ChannelPricingInterface) {
            $entity = $entity->getProductVariant()->getProduct();
        } elseif ($entity instanceof ChannelPricingInterface) {
            $entity = $entity->getProductVariant()->getProduct();
        } elseif ($entity instanceof ProductTaxon) {
            $entity = $entity->getProduct();
        }
        if ($this->objectPersister->handlesObject($entity)) {
            if ($this->isObjectIndexable($entity)) {
                $this->scheduledForUpdate[] = $entity;
            } else {
                // Delete if no longer indexable
                $this->scheduleForDeletion($entity);
            }
        }
    }

    /**
     * Checks if the object is indexable or not.
     *
     * @param object $object
     *
     * @return bool
     */
    private function isObjectIndexable($object)
    {
        return $this->indexable->isObjectIndexable(
            $this->config['indexName'],
            $this->config['typeName'],
            $object
        );
    }

    /**
     * Record the specified identifier to delete. Do not need to entire object.
     *
     * @param object $object
     */
    private function scheduleForDeletion($object)
    {
        if ($identifierValue = $this->propertyAccessor->getValue($object, $this->config['identifier'])) {
            $this->scheduledForDeletion[] = $identifierValue;
        }
    }

    public function postPersist(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();

        if ($entity instanceof ProductVariantInterface) {
            $entity = $entity->getProduct();
        } elseif ($entity instanceof ChannelPricingInterface) {
            $entity = $entity->getProductVariant()->getProduct();
        } elseif ($entity instanceof ChannelPricingInterface) {
            $entity = $entity->getProductVariant()->getProduct();
        } elseif ($entity instanceof ProductTaxon) {
            $entity = $entity->getProduct();
        }
        if ($this->objectPersister->handlesObject($entity)) {
            if ($this->isObjectIndexable($entity)) {
                $this->scheduledForUpdate[] = $entity;
            } else {
                // Delete if no longer indexable
                $this->scheduleForDeletion($entity);
            }
        }
    }

    /**
     * Delete objects preRemove instead of postRemove so that we have access to the id.  Because this is called
     * preRemove, first check that the entity is managed by Doctrine.
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function preRemove(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getObject();

        if ($entity instanceof ProductVariantInterface) {
            $entity = $entity->getProduct();
        } elseif ($entity instanceof ChannelPricingInterface) {
            $entity = $entity->getProductVariant()->getProduct();
        } elseif ($entity instanceof ChannelPricingInterface) {
            $entity = $entity->getProductVariant()->getProduct();
        } elseif ($entity instanceof ProductTaxon) {
            $entity = $entity->getProduct();
        }
        if ($this->objectPersister->handlesObject($entity)) {
            $this->scheduleForDeletion($entity);
        }
    }

    /**
     * Persist scheduled objects to ElasticSearch
     * After persisting, clear the scheduled queue to prevent multiple data updates when using multiple flush calls.
     */
    private function persistScheduled()
    {
        if (count($this->scheduledForInsertion)) {
            $this->objectPersister->insertMany($this->scheduledForInsertion);
            $this->scheduledForInsertion = [];
        }
        if (count($this->scheduledForUpdate)) {
            $this->objectPersister->replaceMany($this->scheduledForUpdate);
            $this->scheduledForUpdate = [];
        }
        if (count($this->scheduledForDeletion)) {
            $this->objectPersister->deleteManyByIdentifiers($this->scheduledForDeletion);
            $this->scheduledForDeletion = [];
        }
    }

    /**
     * Iterating through scheduled actions *after* flushing ensures that the
     * ElasticSearch index will be affected only if the query is successful.
     */
    public function postFlush()
    {
        $this->persistScheduled();
    }
}
