<?php

namespace Wexample\SymfonyLive\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyLiveExtension extends AbstractWexampleSymfonyExtension
{
    public const PARAMETER_HUB_PUBLIC_URL = 'wexample_symfony_live.hub_public_url';
    public const PARAMETER_JWT_SECRET = 'wexample_symfony_live.jwt_secret';
    public const PARAMETER_SUBSCRIBER_TOKEN_TTL = 'wexample_symfony_live.subscriber_token_ttl';

    /**
     * Declares the default hub so an app only has to provide the env vars. Prepended
     * config is merged before the app's own, which therefore still overrides it.
     */
    public function prepend(ContainerBuilder $container): void
    {
        parent::prepend($container);

        $container->prependExtensionConfig('mercure', [
            'hubs' => [
                'default' => [
                    'url' => '%env(MERCURE_URL)%',
                    'public_url' => '%env(MERCURE_PUBLIC_URL)%',
                    'jwt' => [
                        'secret' => '%env(MERCURE_JWT_SECRET)%',
                        // No subscribe claim: this token is the server's, and the server
                        // only publishes. Subscribers get their own scoped token from
                        // LiveSubscriberTokenService.
                        'publish' => '*',
                    ],
                ],
            ],
        ]);
    }

    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $this->loadConfig(
            __DIR__,
            $container
        );

        $config = $this->processConfiguration(
            new Configuration(),
            $configs
        );

        $container->setParameter(
            self::PARAMETER_HUB_PUBLIC_URL,
            $config['hub_public_url']
        );

        $container->setParameter(
            self::PARAMETER_JWT_SECRET,
            $config['jwt_secret']
        );

        $container->setParameter(
            self::PARAMETER_SUBSCRIBER_TOKEN_TTL,
            $config['subscriber_token_ttl']
        );
    }
}
