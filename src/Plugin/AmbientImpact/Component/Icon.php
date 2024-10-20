<?php

namespace Drupal\ambientimpact_icon\Plugin\AmbientImpact\Component;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\ambientimpact_core\ComponentBase;
use Drupal\ambientimpact_icon\IconBundlePluginManager;
use function method_exists;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Icon component.
 *
 * @see \Drupal\ambientimpact_icon\IconBundlePluginManager
 *   This is the Icon Bundle plug-in manager which discovers and manages bundles
 *   for this component.
 *
 * @Component(
 *   id = "icon",
 *   title = @Translation("Icon"),
 *   description = @Translation("Contains settings and methods for managing and rendering icons.")
 * )
 */
class Icon extends ComponentBase {
	/**
	 * The Ambient.Impact Icon Bundle plug-in manager service.
	 *
	 * @var \Drupal\ambientimpact_icon\IconBundlePluginManager
	 */
	private $iconBundleManager;

  /**
   * Constructor; saves dependencies.
   *
   * @param array $configuration
   *   A configuration array containing information about the plug-in instance.
   *
   * @param string $pluginID
   *   The plugin_id for the plug-in instance.
   *
   * @param array $pluginDefinition
   *   The plug-in implementation definition.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The Drupal module handler service.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The Drupal language manager service.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Drupal renderer service.
   *
   * @param \Drupal\Component\Serialization\SerializationInterface $yamlSerialization
   *   The Drupal YAML serialization class.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The Drupal string translation service.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $htmlCacheService
   *   The Component HTML cache service.
   *
   * @param \Drupal\ambientimpact_icon\IconBundlePluginManager $iconBundleManager
	 *   The Ambient.Impact Icon Bundle plug-in manager service.
   */
  public function __construct(
    array $configuration, string $pluginID, array $pluginDefinition,
    ModuleHandlerInterface $moduleHandler,
    LanguageManagerInterface $languageManager,
    RendererInterface $renderer,
    SerializationInterface $yamlSerialization,
    TranslationInterface $stringTranslation,
    CacheBackendInterface $htmlCacheService,
    IconBundlePluginManager $iconBundleManager
  ) {
    // Save dependencies before calling parent::__construct() so that they're
    // available in the configuration methods as ComponentBase::__construct()
    // will call them.
    $this->iconBundleManager = $iconBundleManager;

    parent::__construct(
      $configuration, $pluginID, $pluginDefinition,
      $moduleHandler,
      $languageManager,
      $renderer,
      $yamlSerialization,
      $stringTranslation,
      $htmlCacheService
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration, $pluginID, $pluginDefinition
  ) {
    return new static(
      $configuration, $pluginID, $pluginDefinition,
      $container->get('module_handler'),
      $container->get('language_manager'),
      $container->get('renderer'),
      $container->get('serialization.yaml'),
      $container->get('string_translation'),
      $container->get('cache.ambientimpact_component_html'),
      $container->get('plugin.manager.ambientimpact_icon_bundle')
    );
  }

	/**
	 * {@inheritdoc}
	 */
	public function defaultConfiguration() {
		$defaultBundles = [];

		// Find Icon Bundle plug-in definitions that are defined by this module
		// and save their IDs so that we have a list of the ones that we ship
		// with.
		foreach (
			$this->iconBundleManager->getDefinitions() as $id => $definition
		) {
			if ($definition['provider'] === 'ambientimpact_core') {
				$defaultBundles[] = $id;
			}
		}

		return [
			// These are the bundles that ship with this component.
			'defaultBundles'		=> $defaultBundles,

			// The base class of the icon container, used to derive BEM classes.
			'containerBaseClass'	=> 'ambientimpact-icon',

			// Variable defaults.
			'defaults'				=> [
				// @todo Should we even have a default bundle?
				'bundle'				=> 'core',
				'size'					=> 24,
				'containerTag'			=> 'span',
				'textDisplay'			=> 'visible',
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getJSSettings(): array {
		$config		= $this->configuration;
		$jsSettings	= [
			'containerBaseClass'	=> $config['containerBaseClass'],
			'templateDefaults'		=> $config['defaults'],
		];
		// This array contains the render array for the template values to be
		// rendered with placeholder values for the front-end to use. Note that
		// we don't need to provide placeholders for the class attributes
		// because the front-end uses jQuery to add those, which is cleaner and
		// simpler than text replacement.
		$templateRenderArray	= [
			'#type'					=> 'ambientimpact_icon',
			'#containerTag'			=> 'containerTagPlaceholder',
			'#icon'					=> 'iconNamePlaceholder',
			'#url'					=> 'urlPlaceholder',
			'#size'					=> 'sizePlaceholder',
			'#text'					=> 'textPlaceholder',
		];

    // Render the template without attaching assets or cache metadata.
    if (method_exists($this->renderer, 'renderInIsolation')) {

      // Drupal >= 10.3
      //
      // @see https://www.drupal.org/node/3407994
      //   Change record deprecating RendererInterface::renderPlain() in
      //   favour of RendererInterface::renderInIsolation().
      $jsSettings['template'] = $this->renderer->renderInIsolation(
        $templateRenderArray,
      );

    } else {

      // Drupal < 10.3
      //
      // @todo Remove when minimum core is increased to 10.3 or higher.
      /** @phpstan-ignore-next-line */
      $jsSettings['template'] = $this->renderer->renderPlain(
        $templateRenderArray,
      );

    }

		// Output bundle URLs and their used state.
		foreach (
			$this->iconBundleManager->getIconBundleInstances() as
				$iconBundleID => $iconBundleInstance
		) {
			$jsSettings['bundles'][$iconBundleID] = [
				'url'	=> $iconBundleInstance->getURL(),
				'used'	=> $iconBundleInstance->isUsed(),
			];
		}

		return $jsSettings;
	}
}
