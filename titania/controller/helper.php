<?php
/**
*
* This file is part of the phpBB Customisation Database package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace phpbb\titania\controller;

class helper extends \phpbb\controller\helper
{
	/**
	* Checks whether user is logged in and outputs login box
	* for guests or returns error response for registered users.
	*
	* @return Response object
	*/
	public function needs_auth()
	{
		if (!$this->user->data['is_registered'])
		{
			login_box($this->get_current_url());
		}

		return $this->error($this->user->lang['NO_AUTH'], 403);
	}

	/**
	* {@inheritDoc}
	*/
	public function render($template_file, $page_title = '', $status_code = 200, $display_online_list = false)
	{
		return parent::render($template_file, $this->user->lang($page_title), $status_code, $display_online_list);
	}

	/**
	* {@inheritDoc}
	*/
	public function error($message, $code = 500)
	{
		return parent::error($this->user->lang($message), $code);
	}
}