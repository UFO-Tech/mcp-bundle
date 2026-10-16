<?php

declare(strict_types=1);

namespace Ufo\McpBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Ufo\McpBundle\I18n\DefaultToolTextResolver;
use Ufo\McpBundle\I18n\ToolTextResolverInterface;
use Ufo\McpBundle\I18n\TranslatedToolTextResolver;

final class UfoMcpExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('ufo_mcp.options', $config);
        (new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config')))->load('services.yaml');

        // The translation component is optional: an app without a configured
        // "translator" service still gets working titles/descriptions (name/docblock
        // fallback), it just never sees a translated one. TranslatedToolTextResolver
        // is therefore registered here, not via the autowired src/ resource scan,
        // so it only exists at all when a translator is actually available.
        if ($container->has('translator')) {
            $container->register(TranslatedToolTextResolver::class, TranslatedToolTextResolver::class)
                ->setAutowired(true)
                ->setArgument('$translator', new Reference('translator'));
            $container->setAlias(ToolTextResolverInterface::class, TranslatedToolTextResolver::class);
        } else {
            $container->setAlias(ToolTextResolverInterface::class, DefaultToolTextResolver::class);
        }
    }
}
