<?php
/**
*
* Extension Best Answer Package
*
* @copyright (c) 2015 kinerity <http://www.acsyste.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace kinerity\bestanswer\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use kinerity\bestanswer\tables;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

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
	* @param \phpbb\controller\helper               $helper         Controller helper object
	* @param \phpbb\request\request					$request		Request object
	* @param \phpbb\template\template				$template		Template object
	* @param \phpbb\user							$user			User object
	* @param string									$root_path
	* @param string									$php_ext
	* @param string									$table_prefix
	* @access public
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, $root_path, $php_ext, $table_prefix)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->helper = $helper;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->table_prefix = $table_prefix;
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.acp_manage_forums_display_form'		=> 'acp_manage_forums_display_form',
			'core.acp_manage_forums_initialise_data'	=> 'acp_manage_forums_initialise_data',
			'core.acp_manage_forums_request_data'		=> 'acp_manage_forums_request_data',

			'core.permissions'	=> 'permissions',

			'core.user_setup'	=> 'user_setup',

			'core.viewforum_modify_topicrow'				=> 'viewforum_modify_topicrow',
			'core.viewtopic_assign_template_vars_before'	=> 'viewtopic_assign_template_vars_before',
			'core.viewtopic_modify_post_row'				=> 'viewtopic_modify_post_row',
			'core.viewtopic_cache_user_data'				=> 'viewtopic_cache_user_data',
		);
	}

	public function acp_manage_forums_display_form($event)
	{
		$template_data = $event['template_data'];
		$template_data['S_BESTANSWER_ENABLED'] = $event['forum_data']['bestanswer_enabled'];
		$event['template_data'] = $template_data;
	}

	public function acp_manage_forums_initialise_data($event)
	{
		if ($event['action'] == 'add')
		{
			$forum_data = $event['forum_data'];
			$forum_data = array_merge($forum_data, array(
				'bestanswer_enabled'	=> false,
			));
			$event['forum_data'] = $forum_data;
		}
	}

	public function acp_manage_forums_request_data($event)
	{
		$forum_data = $event['forum_data'];
		$forum_data['bestanswer_enabled'] = $this->request->variable('bestanswer_enabled', 0);
		$event['forum_data'] = $forum_data;
	}

	public function permissions($event)
	{
		$permissions = $event['permissions'];

		$permissions['f_mark_bestanswer'] = array('lang' => 'ACL_F_MARK_BESTANSWER', 'cat' => 'actions');
		$permissions['m_mark_bestanswer'] = array('lang' => 'ACL_M_MARK_BESTANSWER', 'cat' => 'post_actions');

		$event['permissions'] = $permissions;
	}

	public function user_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name'	=> 'kinerity/bestanswer',
			'lang_set'	=> 'common',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	public function viewforum_modify_topicrow($event)
	{
		$topic_row = $event['topic_row'];
		$row = $event['row'];

		$sql = 'SELECT bestanswer_enabled
			FROM ' . FORUMS_TABLE . ' 
			WHERE forum_id = ' . (int) $row['forum_id'];
		$result = $this->db->sql_query($sql);
		$bestanswer_enabled = (int) $this->db->sql_fetchfield('bestanswer_enabled');
		$this->db->sql_freeresult($result);

		$sql = 'SELECT *
			FROM ' . $this->table_prefix . tables::TOPICS_ANSWER . '
			WHERE topic_id = ' . (int) $row['topic_id'];
		$result = $this->db->sql_query($sql);
		while ($data = $this->db->sql_fetchrow($result))
		{
			$topic_id = (int) $data['topic_id'];
		}
		$this->db->sql_freeresult($result);

		if ($bestanswer_enabled)
		{
			$topic_row['S_ANSWERED'] = isset($topic_id) && $row['topic_id'] == $topic_id ? true : false;
		}

		$event['topic_row'] = $topic_row;
	}

	public function viewtopic_assign_template_vars_before($event)
	{
		$topic_data = $event['topic_data'];

		$bestanswer_enabled = (int) $topic_data['bestanswer_enabled'];

		$sql = 'SELECT post_id
			FROM ' . $this->table_prefix . tables::TOPICS_ANSWER . '
			WHERE topic_id = ' . (int) $topic_data['topic_id'];
		$result = $this->db->sql_query($sql);
		$post_id = (int) $this->db->sql_fetchfield('post_id');
		$this->db->sql_freeresult($result);

		if ($bestanswer_enabled && $post_id)
		{
			$this->template->assign_vars(array(
				'S_ANSWERED'	=> true,
			));
		}
	}

	public function viewtopic_modify_post_row($event)
	{
		$post_row = $event['post_row'];
		$row = $event['row'];
		$topic_data = $event['topic_data'];
		$user_poster_data = $event['user_poster_data'];

		$post_id = $topic_id = $user_id = 0;

		$bestanswer_enabled = (int) $topic_data['bestanswer_enabled'];
		$forum_id = (int) $topic_data['forum_id'];
		$topic_poster = (int) $topic_data['topic_poster'];
		$topic_first_post_id = (int) $topic_data['topic_first_post_id'];

		$sql = 'SELECT *
			FROM ' . $this->table_prefix . tables::TOPICS_ANSWER . '
			WHERE topic_id = ' . (int) $topic_data['topic_id'];
		$result = $this->db->sql_query($sql);
		while ($data = $this->db->sql_fetchrow($result))
		{
			$post_id = (int) $data['post_id'];
			$topic_id = (int) $data['topic_id'];
			$user_id = (int) $data['user_id'];
		}
		$this->db->sql_freeresult($result);

		if ($bestanswer_enabled)
		{
			$post_row['S_ANSWERED'] = $post_id ? true : false;
			$post_row['S_ANSWER'] = $post_id == $row['post_id'] ? true : false;
			$post_row['S_AUTH'] = $this->auth->acl_get('m_mark_bestanswer', $forum_id) || ($this->auth->acl_get('f_mark_bestanswer', $forum_id) && $topic_poster == $this->user->data['user_id']) ? true : false;
			$post_row['S_FIRST_POST'] = $topic_first_post_id == $row['post_id'] ? true : false;

			$post_row['U_ANSWER'] = append_sid("{$this->root_path}viewtopic.{$this->php_ext}", 'f=' . $forum_id . '&amp;t=' . $topic_id . '&#35;p' . $post_id);
			$post_row['U_MARK_ANSWER'] = $this->helper->route('kinerity_bestanswer_main_controller', array('action' => 'mark_answer', 'f' => $forum_id, 't' => $topic_data['topic_id'], 'p' => $row['post_id']));
			$post_row['U_UNMARK_ANSWER'] = $this->helper->route('kinerity_bestanswer_main_controller', array('action' => 'unmark_answer', 'f' => $forum_id, 't' => $topic_data['topic_id'], 'p' => $row['post_id']));

			if ($post_id)
			{
				$sql = 'SELECT p.*, u.user_id, u.username, u.user_colour
					FROM ' . POSTS_TABLE . ' p, ' . USERS_TABLE . ' u
					WHERE p.post_id = ' . (int) $post_id . '
						AND p.poster_id = u.user_id';
				$result = $this->db->sql_query($sql);
				while ($data = $this->db->sql_fetchrow($result))
				{
					$bbcode_options = (($data['enable_bbcode']) ? OPTION_FLAG_BBCODE : 0) +
						(($data['enable_smilies']) ? OPTION_FLAG_SMILIES : 0) +
						(($data['enable_magic_url']) ? OPTION_FLAG_LINKS : 0);
					$post_row['ANSWER'] = generate_text_for_display($data['post_text'], $data['bbcode_uid'], $data['bbcode_bitfield'], $bbcode_options);
					$post_row['ANSWER_AUTHOR_FULL'] = get_username_string('full', $data['user_id'], $data['username'], $data['user_colour']);
					$post_row['ANSWER_DATE'] = $this->user->format_date($data['post_time']);
				}
				$this->db->sql_freeresult($result);
			}
		}

		$post_row['TOPICS_ANSWERED'] = $user_poster_data['topics_answered'];

		$event['post_row'] = $post_row;
	}

	public function viewtopic_cache_user_data($event)
	{
		$poster_id = $event['poster_id'];
		$user_cache_data = $event['user_cache_data'];

		$sql = 'SELECT COUNT(user_id) AS topics_answered
			FROM ' . $this->table_prefix . tables::TOPICS_ANSWER . '
			WHERE user_id = ' . (int) $poster_id;
		$result = $this->db->sql_query($sql);
		$topics_answered = (int) $this->db->sql_fetchfield('topics_answered');
		$this->db->sql_freeresult($result);

		$user_cache_data['topics_answered'] = (int) $topics_answered;

		$event['user_cache_data'] = $user_cache_data;
	}
}
