<?php

namespace Oro\Bundle\ApiBundle\Processor\Create;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

use Symfony\Component\HttpFoundation\Response;

use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;
use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Processor\SingleItemContext;
use Oro\Bundle\ApiBundle\Request\Constraint;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;

/**
 * Saves new ORM entity to the database and save its identifier into the Context.
 */
class SaveEntity implements ProcessorInterface
{
    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /**
     * @param DoctrineHelper $doctrineHelper
     */
    public function __construct(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var SingleItemContext $context */

        $entity = $context->getResult();
        if (!is_object($entity)) {
            // entity does not exist
            return;
        }

        $em = $this->doctrineHelper->getEntityManager($entity, false);
        if (!$em) {
            // only manageable entities are supported
            return;
        }

        $em->persist($entity);
        try {
            $em->flush();
        } catch (UniqueConstraintViolationException $e) {
            $context->addError(
                Error::createValidationError(Constraint::CONFLICT, 'The entity already exists')
                    ->setStatusCode(Response::HTTP_CONFLICT)
                    ->setInnerException($e)
            );
        }

        // save entity id into the Context
        if (!$context->hasErrors()) {
            $metadata = $em->getClassMetadata(ClassUtils::getClass($entity));
            $id = $metadata->getIdentifierValues($entity);
            if (!empty($id)) {
                if (1 === count($id)) {
                    $id = reset($id);
                }
                $context->setId($id);
            }
        }
    }
}
