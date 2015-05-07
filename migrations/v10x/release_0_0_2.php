<?php
/**
*
* Extension Best Answer Package
*
* @copyright (c) 2015 kinerity <http://www.acsyste.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace kinerity\bestanswer\migrations\v10x;

use kinerity\bestanswer\tables;

class release_0_0_2 extends \phpbb\db\migration\migration
{
	/**
	* Assign migration file dependencies for this migration
	*
	* @return array Array of migration files
	* @static
	* @access public
	*/
	static public function depends_on()
	{
		return array('\kinerity\bestanswer\migrations\v10x\release_0_0_1');
	}
	/**
	* Add or update schema in the database
	*
	* @return array Array of table schema
	* @access public
	*/
	public function update_schema()
	{
		return array(
			'drop_columns'	=> array(
				$this->table_prefix . 'topics'	=> array(
					'bestanswer_post_id',
				),
			),
			'add_tables'	=> array(
				$this->table_prefix . tables::TOPICS_ANSWER	=> array(
					'COLUMNS'	=> array(
						'topic_id'	=> array('UINT', 0),
						'post_id'	=> array('UINT', 0),
						'user_id'	=> array('UINT', 0),
					),
					'PRIMARY_KEY'	=> 'topic_id',
				),
			),
		);
	}

	/**
	* Drop schema from the database
	*
	* @return array Array of table schema
	* @access public
	*/
	public function revert_schema()
	{
		return array(
			'drop_tables'	=> array(
				$this->table_prefix . tables::TOPICS_ANSWER,
			),
		);
	}
}
