<?php

namespace Wexample\SymfonyLive\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Voter\AbstractEntityVoter;
use Wexample\SymfonyLive\Enum\LiveTopicAction;
use Wexample\SymfonyLive\Helper\LiveTopicHelper;
use Wexample\SymfonyLive\Service\LiveEntityRegistryService;
use Wexample\SymfonyLive\Service\LiveSubscriberTokenService;

#[Route(path: '_live/', name: 'wexample_symfony_live_')]
class LiveSubscribeController extends AbstractController
{
    final public const string ROUTE_SUBSCRIBE_INFO = 'subscribe_info';

    final public const string ROUTE_SUBSCRIBE_TOPICS = 'subscribe_topics';

    /** More than a page has any use for: the url carrying them has a length too. */
    private const int MAX_TOPICS = 100;

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

    /**
     * One token for every topic a page listens to, so the page holds one stream
     * instead of one per entity — see js-api-entity LiveUpdatesMultiplexer.
     *
     * The topics are the ones subscribeInfo() handed out, each read back to its
     * entity and granted on the same terms: open to subscription for that
     * action, and visible to whoever asks. One refusal refuses the whole token,
     * rather than signing for less than the page will listen to.
     */
    #[Route(
        path: 'subscribe-info',
        name: self::ROUTE_SUBSCRIBE_TOPICS,
        options: AbstractController::ROUTE_OPTIONS_ONLY_EXPOSE,
        methods: AbstractController::ROUTE_OPTIONS_METHOD_ONLY_GET
    )]
    public function subscribeTopics(
        Request $request,
        LiveEntityRegistryService $registry,
        LiveSubscriberTokenService $subscriberTokens,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $topics = $request->query->all('topic');

        if (! $topics || count($topics) > self::MAX_TOPICS) {
            throw new BadRequestHttpException('Between 1 and '.self::MAX_TOPICS.' topics are expected');
        }

        $granted = [];

        foreach (array_unique($topics) as $topic) {
            $parsed = is_string($topic) ? LiveTopicHelper::parseEntity($topic) : null;

            // The id held to the same requirement as subscribeInfo()'s: anything else
            // would reach the database as a value it cannot convert.
            if (! $parsed || ! Uuid::isValid($parsed['id']) || ! ($definition = $registry->find($parsed['name']))) {
                throw new NotFoundHttpException('No entity is open to live subscription on '.$topic);
            }

            if (! in_array(LiveTopicAction::tryFrom($parsed['action']), $definition->actions, true)) {
                throw new NotFoundHttpException('No '.$parsed['action'].' is published on '.$parsed['name']);
            }

            $entityKey = $parsed['name'].'/'.$parsed['id'];

            // Several actions of one entity: it is loaded and voted on once.
            if (! isset($granted[$entityKey])) {
                if (! $entity = $entityManager->find($definition->className, $parsed['id'])) {
                    throw new NotFoundHttpException('No '.$parsed['name'].' with id '.$parsed['id']);
                }

                $this->denyAccessUnlessGranted(AbstractEntityVoter::VIEW, $entity);
                $granted[$entityKey] = [];
            }

            $granted[$entityKey][] = $topic;
        }

        return new JsonResponse(
            $subscriberTokens->buildSubscriberInfo(array_merge(...array_values($granted)))->toArray()
        );
    }
}
