<?php
/**
 *
 * @package titania
 * @version $Id$
 * @copyright (c) 2008 Customisation Database Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
* @ignore
*/
if (!defined('IN_TITANIA'))
{
	exit;
}

titania::add_lang('faq');

$faq_id		= request_var('f', 0);
$action 	= request_var('action', '');
$submit		= isset($_POST['submit']) ? true : false;

// Setup faq object
$faq = new titania_faq($faq_id);

if ($faq_id)
{
	if (!$faq->contrib_id)
	{
		// Faq does not exist
		trigger_error('FAQ_NOT_FOUND');
	}

	load_contrib($faq->contrib_id);
}
else
{
	load_contrib();
}

switch ($action)
{
	case 'create':
	case 'edit':
		if (!phpbb::$auth->acl_get('titania_faq_mod') && !phpbb::$auth->acl_get('titania_faq_' . $action) && !titania::$contrib->is_author && !titania::$contrib->is_active_coauthor)
		{
			trigger_error('NO_AUTH');
		}

		// Load the message object
		$message = new titania_message($faq);
		$message->set_auth(array(
			'bbcode'	=> phpbb::$auth->acl_get('titania_bbcode'),
			'smilies'	=> phpbb::$auth->acl_get('titania_smilies'),
		));

		if ($submit)
		{
			$faq->post_data($message);

			$error = $faq->validate();
			$error = array_merge($error, $message->error);

			if (($validate_form_key = $message->validate_form_key()) !== false)
			{
				$error[] = $validate_form_key;
			}

			if (sizeof($error))
			{
				phpbb::$template->assign_var('ERROR', implode('<br />', $error));
			}
			else
			{
				$faq->submit();
				$message->submit($faq->faq_id);

				redirect($faq->get_url());
			}
		}

		$message->display();

		phpbb::$template->assign_vars(array(
			'L_POST_A'			=> phpbb::$user->lang[(($action == 'edit') ? 'EDIT_FAQ' : 'CREATE_FAQ')],

			'S_EDIT'			=> true,
			'S_POST_ACTION'		=> $faq->get_url($action, $faq->faq_id),
		));

		titania::page_header((($action == 'edit') ? 'EDIT_FAQ' : 'CREATE_FAQ'));
	break;

	case 'delete':
		if (!phpbb::$auth->acl_get('titania_faq_mod') && !phpbb::$auth->acl_get('titania_faq_delete') && !titania::$contrib->is_author && !titania::$contrib->is_active_coauthor)
		{
			trigger_error('NO_AUTH');
		}

		if (titania::confirm_box(true))
		{
			$faq->delete();

			// fix an entries order
			$faq->cleanup_order();

			redirect(titania::$contrib->get_url('faq'));
		}
		else
		{
			titania::confirm_box(false, 'DELETE_FAQ', $faq->get_url('delete'));
		}

		redirect(titania::$contrib->get_url('faq'));

	break;

	case 'move_up':
	case 'move_down':
		if (!phpbb::$auth->acl_get('titania_faq_mod') && !titania::$contrib->is_author && !titania::$contrib->is_active_coauthor)
		{
			trigger_error('NO_AUTH');
		}

		$faq->move($action);

		redirect(titania::$contrib->get_url('faq'));

	break;

	default:
		if ($faq_id)
		{
			titania::page_header('FAQ_DETAILS');

			if ($faq->faq_access < titania::$access_level)
			{
				trigger_error('NOT_AUTHORISED');
			}

			// increase a FAQ views counter
			$faq->increase_views_counter();

			// tracking
			titania_tracking::track(TITANIA_FAQ, $faq_id);

			phpbb::$template->assign_vars(array(
				'FAQ_SUBJECT'		=> $faq->faq_subject,
				'FAQ_TEXT'			=> $faq->generate_text_for_display(),
				'FAQ_VIEWS'			=> $faq->faq_views,

				'S_DETAILS'			=> true,

				'U_EDIT_FAQ'		=> (titania::$contrib->is_author || phpbb::$auth->acl_get('titania_faq_edit')) ? $faq->get_url('edit') : false,
			));
		}
		else
		{
			titania::page_header('FAQ_LIST');

			titania::_include('functions_display', 'titania_topic_folder_img');

			// Setup the pagination tool
			$pagination = new titania_pagination();
			$pagination->default_limit = phpbb::$config['topics_per_page'];
			$pagination->request();
			$faqs = array();

			$sql_ary = array(
				'SELECT' => 'f.*',
				'FROM'		=> array(
					TITANIA_CONTRIB_FAQ_TABLE => 'f',
				),
				'WHERE' => 'f.contrib_id = ' . titania::$contrib->contrib_id . '
						AND f.faq_access >= ' . titania::$access_level,
				'ORDER_BY'	=> 'f.faq_order_id DESC',
			);

			// Main SQL Query
			$sql = phpbb::$db->sql_build_query('SELECT', $sql_ary);

			// Handle pagination
			$pagination->sql_count($sql_ary, 'faq_id');
			$pagination->build_pagination($faq->get_url());

			// Get the data
			$result = phpbb::$db->sql_query_limit($sql, $pagination->limit, $pagination->start);

			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$faqs[$row['faq_id']] = $row;
			}
			phpbb::$db->sql_freeresult($result);

			// Grab the tracking info
			titania_tracking::get_tracks(TITANIA_FAQ, array_keys($faqs));

			// Output
			foreach ($faqs as $id => $row)
			{
				// @todo probably should setup an edit time or something for better read tracking in case it was edited
				$folder_img = $folder_alt = '';
				$unread = (titania_tracking::get_track(TITANIA_FAQ, $id, true) === 0) ? true : false;
				titania_topic_folder_img($folder_img, $folder_alt, 0, $unread);

				phpbb::$template->assign_block_vars('faqlist', array(
					'U_FAQ'			=> $faq->get_url('', $row['faq_id']),

					'SUBJECT'		=> $row['faq_subject'],
					'VIEWS'			=> $row['faq_views'],

					'TOPIC_FOLDER_IMG'				=> phpbb::$user->img($folder_img, $folder_alt),
					'TOPIC_FOLDER_IMG_SRC'			=> phpbb::$user->img($folder_img, $folder_alt, false, '', 'src'),
					'TOPIC_FOLDER_IMG_ALT'			=> phpbb::$user->lang[$folder_alt],
					'TOPIC_FOLDER_IMG_ALT'			=> phpbb::$user->lang[$folder_alt],
					'TOPIC_FOLDER_IMG_WIDTH'		=> phpbb::$user->img($folder_img, '', false, '', 'width'),
					'TOPIC_FOLDER_IMG_HEIGHT'		=> phpbb::$user->img($folder_img, '', false, '', 'height'),

					'U_MOVE_UP'		=> (phpbb::$auth->acl_get('titania_faq_mod') || titania::$contrib->is_author) ? $faq->get_url('move_up', $row['faq_id']) : false,
					'U_MOVE_DOWN'	=> (phpbb::$auth->acl_get('titania_faq_mod') || titania::$contrib->is_author) ? $faq->get_url('move_down', $row['faq_id']) : false,
					'U_EDIT'		=> (phpbb::$auth->acl_get('titania_faq_mod') || phpbb::$auth->acl_get('titania_faq_edit') || titania::$contrib->is_author) ? $faq->get_url('edit', $row['faq_id']) : false,
					'U_DELETE'		=> (phpbb::$auth->acl_get('titania_faq_mod') || phpbb::$auth->acl_get('titania_faq_delete') || titania::$contrib->is_author) ? $faq->get_url('delete', $row['faq_id']) : false,
				));
			}

			phpbb::$template->assign_vars(array(
				'ICON_MOVE_UP'				=> '<img src="' . titania::$absolute_board . 'adm/images/icon_up.gif" alt="' . phpbb::$user->lang['MOVE_UP'] . '" title="' . phpbb::$user->lang['MOVE_UP'] . '" />',
				'ICON_MOVE_UP_DISABLED'		=> '<img src="' . titania::$absolute_board . 'adm/images/icon_up_disabled.gif" alt="' . phpbb::$user->lang['MOVE_UP'] . '" title="' . phpbb::$user->lang['MOVE_UP'] . '" />',
				'ICON_MOVE_DOWN'			=> '<img src="' . titania::$absolute_board . 'adm/images/icon_down.gif" alt="' . phpbb::$user->lang['MOVE_DOWN'] . '" title="' . phpbb::$user->lang['MOVE_DOWN'] . '" />',
				'ICON_MOVE_DOWN_DISABLED'	=> '<img src="' . titania::$absolute_board . 'adm/images/icon_down_disabled.gif" alt="' . phpbb::$user->lang['MOVE_DOWN'] . '" title="' . phpbb::$user->lang['MOVE_DOWN'] . '" />',
				'ICON_EDIT'					=> '<img src="' . titania::$absolute_board . 'adm/images/icon_edit.gif" alt="' . phpbb::$user->lang['EDIT'] . '" title="' . phpbb::$user->lang['EDIT'] . '" />',
				'ICON_EDIT_DISABLED'		=> '<img src="' . titania::$absolute_board . 'adm/images/icon_edit_disabled.gif" alt="' . phpbb::$user->lang['EDIT'] . '" title="' . phpbb::$user->lang['EDIT'] . '" />',
				'ICON_DELETE'				=> '<img src="' . titania::$absolute_board . 'adm/images/icon_delete.gif" alt="' . phpbb::$user->lang['DELETE'] . '" title="' . phpbb::$user->lang['DELETE'] . '" />',
				'ICON_DELETE_DISABLED'		=> '<img src="' . titania::$absolute_board . 'adm/images/icon_delete_disabled.gif" alt="' . phpbb::$user->lang['DELETE'] . '" title="' . phpbb::$user->lang['DELETE'] . '" />',

				'S_LIST'					=> true,

				'U_CREATE_FAQ'				=> (phpbb::$auth->acl_get('titania_faq_mod') || phpbb::$auth->acl_get('titania_faq_create') || titania::$contrib->is_author) ? $faq->get_url('create') : false,
			));
		}
	break;
}

phpbb::$template->assign_vars(array(
	'CONTRIB_NAME'		=> titania::$contrib->contrib_name,
));

titania::page_footer(false, 'contributions/contribution_faq.html');