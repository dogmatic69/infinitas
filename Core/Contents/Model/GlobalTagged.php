<?php
	/**
	 * CakePHP Tags Plugin
	 *
	 * Copyright 2009 - 2010, Cake Development Corporation
	 *						1785 E. Sahara Avenue, Suite 490-423
	 *						Las Vegas, Nevada 89104
	 *
	 *
	 *
	 *
	 * @copyright 2009 - 2010, Cake Development Corporation (http://cakedc.com)
	 * @link	  http://github.com/CakeDC/Tags
	 * @package Infinitas.Contents.Model
	 * @license   MIT License (http://www.opensource.org/licenses/mit-license.php)
	 */

	/**
	 * Short description for class.
	 *
	 * @package Infinitas.Contents.Model
	 */

	class GlobalTagged extends ContentsAppModel {
		/**
		 * Table that is used
		 *
		 * @var string
		 */
		public $useTable = 'global_tagged';

		/**
		 * Find methodes
		 *
		 * @var array
		 */
		public $findMethods = array(
			'cloud' => true,
			'tagged' => true
		);

		/**
		 * belongsTo associations
		 *
		 * @var string
		 */
		public $belongsTo = array(
			'GlobalTag' => array(
				'className' => 'Contents.GlobalTag',
				'foreignKey' => 'tag_id'
			)
		);

		public $trashable = false;

		/**
		 * Returns a tag cloud
		 *
		 * The result contains a "weight" field which has a normalized size of the tag
		 * occurrence set. The min and max size can be set by passing 'minSize" and
		 * 'maxSize' to the query. This value can be used in the view to controll the
		 * size of the tag font.
		 *
		 * @todo Ideas to improve this are welcome
		 * @param string
		 * @param array
		 * @param array
		 * 
		 * @return array
		 */
		public function _findCloud($state, $query, $results = array()) {
			if ($state == 'before') {
				$options = array(
					'minSize' => 10,
					'maxSize' => 20,
					'page' => null,
					'limit' => null,
					'order' => null,
					'joins' => array(),
					'offset' => null,
					'contain' => 'GlobalTag',
					'conditions' => array(),
					'fields' => array(
						'GlobalTag.*',
						'GlobalTagged.tag_id',
						'COUNT(*) AS occurrence'
					),
					'group' => array(
						'GlobalTagged.tag_id'
					)
				);

				foreach ($query as $key => $value) {
					if (!empty($value)) {
						$options[$key] = $value;
					}
				}
				$query = $options;

				if(!empty($query['model']) || !empty($query['category'])) {
					$query['joins'][] = array(
						'table' => 'global_contents',
						'alias' => 'GlobalContent',
						'type' => 'LEFT',
						'conditions' => array(
							'GlobalContent.id = GlobalTagged.foreign_key'
						)
					);
				}

				if (!empty($query['model'])) {
					$query['conditions'] = Set::merge(
						$query['conditions'],
						array(
							'or' => array(
								'GlobalTagged.model' => $query['model'],
								'GlobalContent.model' => $query['model']
							)
						)
					);
				}

				if (!empty($query['foreign_key'])) {
					$query['conditions'][] = array(
						$this->alias . '.foreign_key' => $query['foreign_key']
					);
				}

				if (!empty($query['category'])) {
					$query['joins'][] = array(
						'table' => 'global_categories',
						'alias' => 'GlobalContentCategory',
						'type' => 'LEFT',
						'conditions' => array(
							'GlobalContentCategory.id = GlobalContent.global_category_id'
						)
					);
					$query['joins'][] = array(
						'table' => 'global_contents',
						'alias' => 'GlobalContentCategoryData',
						'type' => 'LEFT',
						'conditions' => array(
							'GlobalContentCategoryData.foreign_key = GlobalContentCategory.id'
						)
					);
					$query['conditions'] = Set::merge(
						$query['conditions'],
						array(
							'GlobalContentCategoryData.slug' => $query['category']
						)
					);
				}
				unset($query['model'], $query['category'], $query['foreign_key']);
				return $query;
			}

			elseif ($state == 'after') {
				if (!empty($results) && isset($results[0][0]['occurrence'])) {
					$weights = Set::extract($results, '{n}.0.occurrence');
					$maxWeight = max($weights);
					$minWeight = min($weights);

					$spread = $maxWeight - $minWeight;
					if (0 == $spread) {
						$spread = 1;
					}

					foreach ($results as $key => $result) {
						$size = $query['minSize'] + (($result[0]['occurrence'] - $minWeight) * (($query['maxSize'] - $query['minSize']) / ($spread)));
						$results[$key]['Tag']['occurrence'] = $result[0]['occurrence'];
						$results[$key]['Tag']['weight'] = ceil($size);
					}
				}

				return $results;
			}
		}

		/**
		 * Find all the Model entries tagged with a given tag
		 *
		 * The query must contain a Model name, and can contain a 'by' key with the Tag keyname to filter the results
		 * <code>
		 * $this->Article->GlobalTagged->find('tagged', array(
		 *		'by' => 'cakephp',
		 *		'model' => 'Article'));
		 * </code>
		 *
		 * @TODO Find a way to populate the "magic" field Article.tags
		 * @param string $state
		 * @param array $query
		 * @param array $results
		 * @return mixed Query array if state is before, array of results or integer (count) if state is after
		 */
		public function _findTagged($state, $query, $results = array()) {
			if ($state == 'before') {
				$Model = ClassRegistry::init($query['model']);
				if (isset($query['model']) && is_a($Model, 'Model')) {
					$belongsTo = array(
						$Model->alias => array(
							'className' => $Model->name,
							'foreignKey' => 'foreign_key',
							'conditions' => array(
								$this->alias . '.model' => $Model->alias
							),
						)
					);

					$this->bindModel(compact('belongsTo'));

					if (isset($query['operation']) && $query['operation'] == 'count') {
						$query['fields'][] = 'COUNT(DISTINCT ' . $Model->alias . '.' . $Model->primaryKey . ')';
					}

					else {
						$query['fields'][] = 'DISTINCT ' . $Model->alias . '.*';
					}

					if (!empty($query['by'])) {
						$query['conditions'] = array(
							$this->GlobalTag->alias . '.keyname' => $query['by']);
					}
				}

				return $query;
			}

			elseif ($state == 'after') {
				if (isset($query['operation']) && $query['operation'] == 'count') {
					return array_shift($results[0][0]);
				}

				return $results;
			}
		}
	}