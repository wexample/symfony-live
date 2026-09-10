<?php

namespace Wexample\SymfonyLive\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Voter\AbstractEntityVoter;
use Wexample\SymfonyLive\Helper\LiveTopicHelper;
use Wexample\SymfonyLive\Service\LiveEntityRegistryService;
use Wexample\SymfonyLive\Service\LiveSubscriberTokenService;

#[Route(path: 'live/', name: 'wexample_symfony_live_')]
class LiveSubscribeController extends AbstractController
{
    final public const string ROUTE_SUBSCRIBE_INFO = 'subscribe_info';

    /**
     * The caller names an entity, never a topic: the topics it gets back are the ones the
     * server itself publishes on, so the two halves cannot drift apart.
     */
    #[Route(
        path: 'subscribe-info/{entityName}/{id}',
        name: self::ROUTE_SUBSCRIBE_INFO,
        requirements: ['id' => Requirement::UUID],
        options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE,
        methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_GET
    )]
    public function subscribeInfo(
        string $entityName,
        string $id,
        LiveEntityRegistryService $registry,
        LiveSubscriberTokenService $subscriberTokens,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (! $definition = $registry->find($entityName)) {
            throw new NotFoundHttpException('No entity is open to live subscription under the name '.$entityName);
        }

        if (! $entity = $entityManager->find($definition->className, $id)) {
            throw new NotFoundHttpException('No '.$entityName.' with id '.$id);
        }

        // With no voter supporting the entity this denies, which is the safe way round:
        // the attribute opens an entity to subscription, the app still says who may listen.
        $this->denyAccessUnlessGranted(AbstractEntityVoter::VIEW, $entity);

        $topics = [];
        foreach ($definition->actions as $action) {
            $topics[] = LiveTopicHelper::entity($entity, $action);
        }

        return new JsonResponse(
            $subscriberTokens->buildSubscriberInfo($topics)->toArray()
        );
    }
}
