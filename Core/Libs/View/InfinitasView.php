<?php
	/**
	 * Infinitas View
	 *
	 * makes the mustache templating class available in the views, and extends
	 * the Theme View to allow the use of themes.
	 *

	 *
	 * @filesource
	 * @copyright Copyright (c) 2010 Carl Sutton ( dogmatic69 )
	 * @link http://www.infinitas-cms.org
	 * @package Infinitas.Libs.View
	 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
	 * @since 0.8a
	 *
	 * @author Carl Sutton <dogmatic69@infinitas-cms.org>
	 */
	App::uses('Mustache', 'Libs.Lib');

	class InfinitasView extends View {
		/**
		 * place holder for the mustache templating engine.
		 */
		public $Mustache = null;

		/**
		 * internal cache of template parts from the entire system
		 */
		private $__mustacheTemplates = array();

		/**
		 * internal cache of vars that are used in the mustache template rendering
		 */
		private $__vars = array();

		/**
		 * get the
		 */
		public function __construct($Controller, $register = true) {
			$this->Mustache = new Mustache();
			parent::__construct($Controller, $register);

			$this->__setJsVariables();
		}

		/**
		 * render views
		 *
		 * Let cake render the view as per normal, then pass the data to Mustache
		 * to render the data into any templates
		 *
		 * @param string $viewFile the file that is being rendered
		 * @param array $data see cake docs
		 *
		 * @return string
		 */
		protected function _render($viewFile, $data = array()) {
			$this->__loadHelpers();

			$out = parent::_render($viewFile, $data);
			$this->__renderMustache($out);
			$this->__parseSnips($out);

			return $out;
		}

		/**
		 * render any mustache templates with the viewVars
		 *
		 * you can pass ?mustache=false in the url to see the raw output skipping
		 * the template rendering. could be handy for debugging. if debug is off
		 * this has no effect.
		 *
		 * @param string $out the output for the browser
		 *
		 * @return void
		 */
		private function __renderMustache(&$out) {
			if(Configure::read('debug') < 1 && isset($this->request['url']['mustache'])) {
				unset($this->request['url']['mustache']);
			}

			if($this->__skipMustacheRender()) {
				return;
			}

			if(empty($this->__mustacheTemplates)) {
				$this->__mustacheTemplates = array_filter(current($this->Event->trigger('requireGlobalTemplates')));
			}

			foreach($this->__mustacheTemplates as $plugin => $template) {
				$this->__vars['viewVars'][Inflector::classify($plugin) . 'Template'] = $template;
			}

			$this->__vars['viewVars']  = &$this->viewVars;
			$this->__vars['viewVars']['templates'] =& $this->__mustacheTemplates['requireGlobalTemplates'];
			$this->__vars['params']	= &$this->request->params;

			$out = $this->renderTemplate($out, $this->__vars['viewVars']);
		}

		/**
		 * @breif render a mustache template with the passed in variables
		 *
		 * @param string $template the mustache template
		 * @param array $variables variables that will be instered into the template
		 *
		 * @return string
		 */
		public function renderTemplate($template, array $variables = array()) {
			return $this->Mustache->render($template, $variables);
		}

		/**
		 * check if mustache should be used to render a template
		 *
		 * only on for admin or it renders the stuff in the editor which is pointless
		 * could maybe just turn it off for edit or some other work around
		 */
		private function __skipMustacheRender() {
			return (isset($this->request->params['admin']) && $this->request->params['admin']) || !($this->Mustache instanceof Mustache) ||
				(isset($this->request['url']['mustache']) && $this->request['url']['mustache'] == 'false');
		}

		private function __loadHelpers() {
			$helpers = EventCore::trigger($this, 'requireHelpersToLoad');
			foreach($helpers['requireHelpersToLoad'] as $plugin) {
				foreach((array)$plugin as $helper => $config) {
					if(is_int($helper) && is_string($config)) {
						$helper = $config;
						$config = array();
					}

					$this->Helpers->load($helper, $config);
				}
			}
		}

		/**
		 * Set some data for the infinitas js lib.
		 */
		private function __setJsVariables() {
			if($this->request->is('ajax')) {
				return false;
			}
			if (!empty($this->request->params['models'])) {
				$model = current($this->request->params['models']);
			}
			$infinitasJsData['base']	= $this->request->base;
			$infinitasJsData['here']	= $this->request->here;
			$infinitasJsData['plugin']	= $this->request->params['plugin'];
			$infinitasJsData['name']	= $this->name;
			$infinitasJsData['action']	= $this->request->params['action'];
			$infinitasJsData['params']	= $this->request->params;
			$infinitasJsData['passedArgs'] = !empty($this->request->params['pass']) ? $this->request->params['pass'] : array();
			$infinitasJsData['data']	   = $this->request->data;

			$infinitasJsData['model']	   = isset($model) ? $model['className'] : null;

			$infinitasJsData['config']	 = Configure::read();

			$this->set(compact('infinitasJsData'));
		}

		/**
		 * look for and insert dynamic snips
		 *
		 * @param string $out the data for output by reference
		 *
		 * @return void
		 */
		private function __parseSnips(&$out) {
			if(substr($this->request->params['action'], 0, 5) == 'admin') {
				return;
			}

			preg_match_all('/\[(snip|module):([A-Za-z0-9_\-\.\#]*)(.*?)\]/i', $out, $snips);
			$snips = array_unique($snips[0]);

			if(empty($snips)) {
				return;
			}

			foreach($snips as $key => $match) {
				try {
					$pos = strpos($out, $match);
					if ($pos !== false) {
						$out = substr_replace($out, $this->__parseSnipParams($match), $pos, strlen($match));
					}
				}

				catch(Exception $e) {
					$out = str_replace($match, $e->getMessage(), $out);
					continue;
				}
			}
		}

		/**
		 * figure out what was requested and load the module
		 *
		 * @param <type> $match
		 * @return <type>
		 */
		private function __parseSnipParams($match) {
			if(empty($match)) {
				return false;
			}

			$params = array(
				'modelClass' => null,
				'id' => null
			);

			$match = str_replace(array('[', ']'), '', $match);
			if(strstr($match, '#') === false) {
				$match .= '#';
			}

			list($params['modelClass'], $params['id']) = explode('#', $match);
			list($params['type'], $params['modelClass']) = explode(':', $params['modelClass']);
			list($params['plugin'], $params['model']) = pluginSplit($params['modelClass']);

			switch($params['type']) {
				case 'snip':
					return $this->ModuleLoader->loadDirect($params['plugin'] . '.snip', $params);
					break;

				case 'module':
					return $this->ModuleLoader->loadDirect($params['plugin'] . '.' . $params['model'], $params);
					break;
			}
		}
	}