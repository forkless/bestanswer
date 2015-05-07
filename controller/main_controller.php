<?php
/**
*
* Extension Best Answer Package
*
* @copyright (c) 2015 kinerity <http://www.acsyste.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace kinerity\bestanswer\controller;

use kinerity\bestanswer\tables;

/**
* Main controller
*/
class main_controller
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	/** @var string phpbb_root_path */
	protected $root_path;

	/** @var string phpEx */
	protected $php_ext;

	/** @var string table_prefix */
	protected $table_prefix;

	/**
	* Constructor
	*
	* @param \phpbb\auth\auth						$auth			Auth object
	* @param \phpbb\db\driver\driver_interface		$db				Database object
	* @param \phpbb\request\request					$request		Request object
	* @param \phpbb\user							$user			User object
	* @param string									$root_path
	* @param string									$php_ext
	* @param string									$table_prefix
	* @access public
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, \phpbb\request\request $request, \phpbb\user $user, $root_path, $php_ext, $table_prefix)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->request = $request;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->table_prefix = $table_prefix;
	}

	/**
	* Main controller for route /{action}
	*/
	public function change_post_status($action)
	{
		$forum_id = $this->request->variable('f', 0);
		$post_id = $this->request->variable('p', 0);
		$topic_id = $this->request->variable('t', 0);

		if (!$forum_id || !$post_id || !$topic_id)
		{
			throw new \phpbb\exception\http_exception(404, $this->user->lang('INVALID_VARS'));
		}

		$data = array();

		// Populate data array with forum data
		$sql = 'SELECT bestanswer_enabled
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . (int) $forum_id;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$data['bestanswer_enabled'] = $row['bestanswer_enabled'];
		}
		$this->db->sql_freeresult($result);

		// Populate data array with post data
		$sql = 'SELECT topic_id, poster_id
			FROM ' . POSTS_TABLE . '
			WHERE post_id = ' . (int) $post_id;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$data['topic_id'] = $row['topic_id'];
			$data['poster_id'] = $row['poster_id'];
		}
		$this->db->sql_freeresult($result);

		// Populate data array with topic data
		$sql = 'SELECT *
			FROM ' . TOPICS_TABLE . '
			WHERE topic_id = ' . (int) $topic_id;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$data['forum_id'] = $row['forum_id'];
			$data['topic_first_post_id'] = $row['topic_first_post_id'];
			$data['topic_poster'] = $row['topic_poster'];
		}
		$this->db->sql_freeresult($result);

		// Handle all possible errors...
		if (!$data['bestanswer_enabled'])
		{
			throw new \phpbb\exception\http_exception(404, $this->user->lang('EXTENSION_NOT_ENABLED'));
		}

		if (($data['topic_id'] != (int) $topic_id) || ($data['forum_id'] != (int) $forum_id))
		{
			throw new \phpbb\exception\http_exception(404, $this->user->lang('INVALID_VARS'));
		}

		if ($data['topic_first_post_id'] == (int) $post_id)
		{
			throw new \phpbb\exception\http_exception(404, $this->user->lang('TOPIC_FIRST_POST'));
		}

		if (!$this->auth->acl_get('m_mark_bestanswer', (int) $forum_id) && (!$this->auth->acl_get('f_mark_bestanswer', (int) $forum_id) && $data['topic_poster'] != $this->user->data['user_id']))
		{
			throw new \phpbb\exception\http_exception(403, $this->user->lang('NOT_AUTHORISED'));
		}

		switch ($action)
		{
			case 'mark_answer':
				if (confirm_box(true))
				{
					$sql = 'SELECT poster_id
						FROM ' . POSTS_TABLE . '
						WHERE post_id = ' . (int) $post_id;
					$result = $this->db->sql_query($sql);
					$poster_id = (int) $this->db->sql_fetchfield('poster_id');
					$this->db->sql_freeresult($result);

					$sql = 'SELECT topic_id
						FROM ' . $this->table_prefix . tables::TOPICS_ANSWER . '
						WHERE topic_id = ' . (int) $topic_id;
					$result = $this->db->sql_query($sql);
					$data = (int) $this->db->sql_fetchfield('topic_id');
					$this->db->sql_freeresult($result);

					$sql_data = array(
						'topic_id'	=> (int) $topic_id,
						'post_id'	=> (int) $post_id,
						'user_id'	=> (int) $poster_id,
					);

					if (!$data)
					{
						$sql = 'INSERT INTO ' . $this->table_prefix . tables::TOPICS_ANSWER . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
					}
					else
					{
						$sql = 'UPDATE ' . $this->table_prefix . tables::TOPICS_ANSWER . ' SET ' . $this->db->sql_build_array('UPDATE', $sql_data) . ' WHERE topic_id = ' . (int) $topic_id;
					}

					$this->db->sql_query($sql);
				}
				else
				{
					confirm_box(false, $this->user->lang('MARK_ANSWER_CONFIRM'));
				}
			break;

			case 'unmark_answer':
				if (confirm_box(true))
				{
					$sql = 'DELETE FROM ' . $this->table_prefix . tables::TOPICS_ANSWER . '
						WHERE topic_id = ' . (int) $topic_id;
					$this->db->sql_query($sql);
				}
				else
				{
					confirm_box(false, $this->user->lang('UNMARK_ANSWER_CONFIRM'));
				}
			break;
		}

		$params = array(
			't'	=> (int) $topic_id,
		);

		$url = generate_board_url();
		$url .= ((substr($url, -1) == '/') ? '' : '/') . 'viewtopic.' . $this->php_ext;
		$url = append_sid($url, $params);

		redirect($url);
	}
}
