<?php

declare(strict_types=1);

namespace AsyncAws\CodeGenerator\Generator;

use AsyncAws\CodeGenerator\Definition\ServiceDefinition;
use AsyncAws\CodeGenerator\Generator\Naming\ClassName;
use AsyncAws\CodeGenerator\Generator\Naming\NamespaceRegistry;
use AsyncAws\CodeGenerator\Generator\PhpGenerator\ClassRegistry;
use AsyncAws\Core\AwsError\AwsErrorFactoryInterface;
use AsyncAws\Core\AwsError\JsonRestAwsErrorFactory;
use AsyncAws\Core\AwsError\JsonRpcAwsErrorFactory;
use AsyncAws\Core\AwsError\XmlAwsErrorFactory;
use AsyncAws\Core\Configuration;
use AsyncAws\Core\Exception\UnsupportedRegion;
use Nette\PhpGenerator\ClassType;

/**
 * Generate API client.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @internal
 */
class ClientGenerator
{
    /**
     * @var ClassRegistry
     */
    private $classRegistry;

    /**
     * @var NamespaceRegistry
     */
    private $namespaceRegistry;

    public function __construct(ClassRegistry $classRegistry, NamespaceRegistry $namespaceRegistry)
    {
        $this->classRegistry = $classRegistry;
        $this->namespaceRegistry = $namespaceRegistry;
    }

    /**
     * Update the API client with a constants function call.
     */
    public function generate(ServiceDefinition $definition): ClassName
    {
        $className = $this->namespaceRegistry->getClient($definition);
        $classBuilder = $this->classRegistry->register($className->getFqdn(), true);

        $supportedVersions = eval(sprintf('class A%s extends %s {
            public function __construct() {}
            public function getVersion() {
                return array_keys($this->getSignerFactories());
            }
        } return (new A%1$s)->getVersion();', sha1(uniqid('', true)), $className->getFqdn()));

        $endpoints = $definition->getEndpoints();
        $dumpConfig = static function ($config, $region = null) use ($supportedVersions) {
            $signatureVersions = array_intersect($supportedVersions, $config['signVersions']);
            rsort($signatureVersions);

            return strtr('        return [
                "endpoint" => "ENDPOINT",
                "signRegion" => REGION,
                "signService" => SIGN_SERVICE,
                "signVersions" => SIGN_VERSIONS,
            ];' . "\n", [
                'ENDPOINT' => strtr($config['endpoint'], ['%region%' => $region ?? '$region']),
                'REGION' => isset($config['signRegion']) ? var_export($config['signRegion'], true) : (null === $region ? '$region' : var_export($region, true)),
                'SIGN_SERVICE' => var_export($config['signService'], true),
                'SIGN_VERSIONS' => json_encode($signatureVersions),
            ]);
        };
        $sameConfig = static function (array $config, array $defaultConfig, string $region) use ($supportedVersions) {
            $configEndpoint = strtr($config['endpoint'], ['%region%' => $region]);
            $DefaultConfigEndpoint = strtr($defaultConfig['endpoint'], ['%region%' => $region]);
            if ($configEndpoint !== $DefaultConfigEndpoint) {
                return false;
            }

            $configRegion = isset($config['signRegion']) ? $config['signRegion'] : $region;
            $defaultConfigRegion = isset($defaultConfig['signRegion']) ? $defaultConfig['signRegion'] : $region;
            if ($configRegion !== $defaultConfigRegion) {
                return false;
            }

            if ($config['signService'] !== $defaultConfig['signService']) {
                return false;
            }

            $configSignVersions = array_intersect($supportedVersions, $config['signVersions']);
            $defaultConfigSignVersions = array_intersect($supportedVersions, $defaultConfig['signVersions']);
            rsort($configSignVersions);
            rsort($defaultConfigSignVersions);
            if ($configSignVersions !== $defaultConfigSignVersions) {
                return false;
            }

            return true;
        };

        $body = '';
        if (!isset($endpoints['_global']['aws'])) {
            $classBuilder->addUse(Configuration::class);
            $body .= 'if ($region === null) {
                $region = Configuration::DEFAULT_REGION;
            }

            ';
        } else {
            if (empty($endpoints['_global']['aws']['signRegion'])) {
                throw new \RuntimeException('Global endpoint without signRegion is not yet supported');
            }
            $body .= 'if ($region === null) {
                ' . $dumpConfig($endpoints['_global']['aws']) . '
            }

            ';
        }

        $regionSwitchbody = '';
        $defaultConfig = null;
        if (isset($endpoints['_default']['aws'])) {
            $defaultConfig = $endpoints['_default']['aws'];
        } elseif (isset($endpoints['_global']['aws'])) {
            $defaultConfig = $endpoints['_global']['aws'];
        }

        foreach ($endpoints['_global'] ?? [] as $partitionName => $config) {
            if ('aws' === $partitionName && !isset($endpoints['_default']['aws'])) {
                continue;
            }
            if (empty($config['regions'])) {
                continue;
            }
            sort($config['regions']);
            $regions = [];
            foreach ($config['regions'] as $region) {
                if ($defaultConfig && $sameConfig($config, $defaultConfig, $region)) {
                    continue;
                }
                $regions[] = $region;
                $regionSwitchbody .= sprintf("    case %s:\n", var_export($region, true));
            }
            if (\count($regions) > 0) {
                if (1 === \count($regions)) {
                    $regionSwitchbody .= $dumpConfig($config, $regions[0]);
                } else {
                    $regionSwitchbody .= $dumpConfig($config);
                }
            }
        }
        foreach ($endpoints['_default'] ?? [] as $partitionName => $config) {
            if ('aws' === $partitionName) {
                continue;
            }
            if (empty($config['regions'])) {
                continue;
            }
            sort($config['regions']);
            $regions = [];
            foreach ($config['regions'] as $region) {
                if ($defaultConfig && $sameConfig($config, $defaultConfig, $region)) {
                    continue;
                }
                $regions[] = $region;
                $regionSwitchbody .= sprintf("    case %s:\n", var_export($region, true));
            }
            if (\count($regions) > 0) {
                if (1 === \count($regions)) {
                    $regionSwitchbody .= $dumpConfig($config, $regions[0]);
                } else {
                    $regionSwitchbody .= $dumpConfig($config);
                }
            }
        }
        ksort($endpoints);
        foreach ($endpoints as $region => $config) {
            if ('_' === $region[0]) {
                continue; // skip `_default` and `_global`
            }
            if ($defaultConfig && $sameConfig($config, $defaultConfig, $region)) {
                continue;
            }

            $regionSwitchbody .= sprintf("    case %s:\n", var_export($region, true));
            $regionSwitchbody .= $dumpConfig($config, $region);
        }

        if ('' !== $regionSwitchbody) {
            $body .= "switch (\$region) {\n";
            $body .= $regionSwitchbody;
            $body .= '}';
        }

        if (isset($endpoints['_default']['aws'])) {
            $body .= $dumpConfig($endpoints['_default']['aws']);
        } elseif (isset($endpoints['_global']['aws'])) {
            $body .= $dumpConfig($endpoints['_global']['aws']);
        } else {
            $body .= 'throw new UnsupportedRegion(sprintf(\'The region "%s" is not supported by "' . $definition->getName() . '".\', $region));';
        }
        $classBuilder->addUse(UnsupportedRegion::class);

        $classBuilder->addMethod('getEndpointMetadata')
            ->setReturnType('array')
            ->setVisibility(ClassType::VISIBILITY_PROTECTED)
            ->setBody($body)
            ->addParameter('region')
                ->setType('string')
                ->setNullable(true)
        ;

        switch ($definition->getProtocol()) {
            case 'query':
            case 'rest-xml':
                $errorFactory = XmlAwsErrorFactory::class;

                break;
            case 'rest-json':
                $errorFactory = JsonRestAwsErrorFactory::class;

                break;
            case 'json':
                $errorFactory = JsonRpcAwsErrorFactory::class;

                break;
            default:
                throw new \LogicException(sprintf('Parser for "%s" is not implemented yet', $definition->getProtocol()));
        }

        $classBuilder->addUse(AwsErrorFactoryInterface::class);
        $classBuilder->addUse($errorFactory);

        $errorFactoryBase = basename(str_replace('\\', '/', $errorFactory));
        $classBuilder->addMethod('getAwsErrorFactory')
            ->setReturnType(AwsErrorFactoryInterface::class)
            ->setVisibility(ClassType::VISIBILITY_PROTECTED)
            ->setBody("return new $errorFactoryBase();")
        ;

        return $className;
    }
}
