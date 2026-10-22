<?php

namespace Wexample\SymfonyLive\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyLive\Interface\LiveEntityNormalizerInterface;

class WexampleSymfonyLiveExtension extends AbstractWexampleSymfonyExtension
{
    public const PARAMETER_HUB_PUBLIC_URL = 'wexample_symfony_live.hub_public_url';
    public const PARAMETER_JWT_SECRET = 'wexample_symfony_live.jwt_secret';
    public const PARAMETER_SUBSCRIBER_TOKEN_TTL = 'wexample_symfony_live.subscriber_token_ttl';

    /**
     * The hub of a plain install, and a secret long enough for HS256 to sign with. A real
     * deployment overrides them; a build that never reaches a hub boots without them.
     */
    private const DEFAULT_ENV_VARS = [
        'MERCURE_URL' => 'http://localhost/.well-known/mercure',
        'MERCURE_PUBLIC_URL' => 'http://localhost/.well-known/mercure',
        'MERCURE_JWT_SECRET' => '!ChangeThisMercureHubJWTSecretKey!',
    ];

    /**
     * Declares the default hub so an app only has to provide the env vars. Prepended
     * config is merged before the app's own, which therefore still overrides it.
     */
    public function prepend(ContainerBuilder $container): void
    {
        parent::prepend($container);

        // Defaulting the variable, not the config key: this also answers the
        // config/packages/mercure.yaml the Mercure recipe writes into the app, which
        // would otherwise break every console command of an app with no hub configured.
        foreach (self::DEFAULT_ENV_VARS as $name => $value) {
            $container->setParameter('env('.$name.')', $value);
        }

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

        // So an app declares its live normalizer by implementing the interface, with nothing
        // to add in its own services.yaml.
        $container
            ->registerForAutoconfiguration(LiveEntityNormalizerInterface::class)
            ->addTag(LiveEntityNormalizerInterface::TAG);

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
