<?php

if (!function_exists('_T')) {
	function _T($str = '') { return $str; }
}

class Flame
{
	public $ci;
	public $config = array();
	
	public $messages = array();

	function __construct()
	{
		$this->ci = &get_instance();
		$this->ci->load->database();
		$this->ci->load->helper( array('html', 'inflector', 'url') );		
		$this->ci->load->config('flame', TRUE, TRUE);
		
		$this->config = $this->ci->config->item('flame');				
		if (isset($this->ci->session))
		{
			$this->messages = $this->ci->session->userdata('flame-messages');
			$this->ci->session->unset_userdata('flame-messages');
		}
		$this->messages = is_array($this->messages) ? $this->messages : array();
	}

	function initialize($config = array())
	{
		$this->config = array_merge($this->config, $config);
		$this->_translate_fields();

		if (empty($this->config['render_cell']))
		{
			$this->config['render_cell'] = array($this, 'render_cell');
		}
	}

	function action($default = 'listing')
	{
		$action = $this->ci->uri->segment($this->config['uri_segment']);		
		$action = $action ? $action : $default;
		return $action;
	}

	function display($return = FALSE)
	{
		$result = '';

		$action = $this->action();

		if (method_exists($this, $action))
		{
			$result = $this->$action();
		}
		else
		{
			if ($this->config['use_global_functions'] && is_callable($action))
			{
				$result = $action($this);
			}
			else
			{
				show_error( sprintf(_T("Unknown action: '%s'"), $action) );
			}
		}

		if ($return)
		{
			return $result;
		}

		echo $result;
	}

	function listing()
	{
		$result = '';
		$this->ci->load->library('pagination');

		$heading = array();
		$rows    = array();

		$this->_fetch_rows($rows);

		$this->_prepare_rows($rows);

		$this->_prepare_heading($heading);

		$this->ci->table->set_heading($heading);

		$this->ci->table->set_template($this->config['templates']['table']);

		$result .= $this->ci->table->generate($rows);

		$this->config['pagination']['base_url'] = $this->_base_url() .'listing/';
		
		$this->config['pagination']['uri_segment'] = $this->config['uri_segment']+1;

		$this->ci->pagination->initialize($this->config['pagination']);

		$links = $this->ci->pagination->create_links();
		if (trim($links)!='')
		{
			$links = $this->config['templates']['pagination']['pagination_open'] .
					 $links .
					 $this->config['templates']['pagination']['pagination_close'];

	 		if ($this->config['pagination']['placement'] == 'both')
	 		{
	 			$result = $links . $result . $links;
	 		}
	 		else if ($this->config['pagination']['placement'] == 'top')
	 		{
	 			$result = $links . $result;
	 		}
	 		else
	 		{
	 			$result .= $links;
	 		}
		}
		
		$result = $this->_show_messages() . $result;

		if ($this->config['page_title'])
		{
			if ($this->config['page_title']===TRUE)
			{
				$title = _T( plural(humanize($this->config['table'])) );
			}
			else
			{
				$title = $this->config['page_title'];
			}
			$result = '<div class="page-header">' . heading($title, 1) . '</div>' . $result;
		}

		if ($this->config['show_actions'])
		{
			$result .= '
			<div class="form-actions">
				'.anchor( $this->_base_url().'create', _T($this->config['labels']['create']), 'class="btn btn-primary"' ).'
			</div>
			';
		}
		
		if ($this->config['page_title'])
		{
			$result .= '</div>';
		}

		return $result;
	}

	function sort()
	{
		$sort_dir = $this->ci->uri->segment($this->config['uri_segment']+2);
		$sort_dir = in_array($sort_dir, array('asc', 'desc')) ? $sort_dir : 'asc';
		$this->_set_sort_name( $this->ci->uri->segment($this->config['uri_segment']+1) );
		$this->_set_sort_dir ( $sort_dir );
		redirect( $this->_base_url() );
	}

	function create()
	{
		return $this->_form();
	}

	function edit()
	{
		$id = $this->ci->uri->segment($this->config['uri_segment']+1);
		return $this->_form($id);
	}

	function delete()
	{
		$ok = FALSE;
		$id = $this->ci->uri->segment($this->config['uri_segment']+1);
						
		if ($id)
		{
			$query = $this->ci->db->where($this->config['primary_key'], $id)->get($this->config['table']);
			if ($query->num_rows() > 0)
			{
				$ok = $this->ci->db->delete($this->config['table'], array($this->config['primary_key'] => $id), 1);
				if ($ok)
				{
					$this->add_message('Item deleted', 'success', TRUE);
				}
				else
				{
					$this->add_message('Error deleting item', 'error', TRUE);
				}
			}
		}
		
		$suffix = '';
		if ($this->ci->uri->segment($this->config['uri_segment']+2) == 'listing')
		{
			$page = (int)$this->ci->uri->segment($this->config['uri_segment']+3);
			if ($page>1)
			{
				$suffix = 'listing/'.$page;
			}
		}
		
		redirect( $this->_base_url() . $suffix );
	}

	function render_cell($fieldname, $value, $flame)
	{
		$this->ci->load->helper('text');

		if (function_exists('ellipsize'))
		{
			return ellipsize($value, 128, 0.5);
		}
		return NULL;
	}
	
	function add_message($message, $type = 'info', $flash = FALSE)
	{
		$this->messages[] = array(
			'message' => $message,
			'type'    => $type,
		);
						
		if ($flash && isset($this->ci->session))
		{
			$this->ci->session->set_userdata('flame-messages', $this->messages);			
		}		
	}

	function _fetch_rows(&$rows)
	{
		$rows      = $this->config['rows'];
		$sort_name = $this->_sort_name();
		$sort_dir  = $this->_sort_dir();
		$cur_page  = $this->_current_page();

		if ($this->config['table'])
		{
			$this->ci->load->library('table');
			if ( (int)$this->config['pagination']['total_rows'] == 0)
			{
				$this->config['pagination']['total_rows'] = $this->ci->db->count_all($this->config['table']);
			}
			if ($sort_name && $sort_dir)
			{
				$this->ci->db->order_by($sort_name, $sort_dir);
			}

			$offset = ($cur_page-1) * $this->config['pagination']['per_page'];
			$query  = $this->ci->db->select('*')->get($this->config['table'], $this->config['pagination']['per_page'], $offset);
			$rows   = $query->result_array();
		}

		$rows = is_array($rows) ? $rows : array();
	}

	function _current_page()
	{
		$cur_page = (int)$this->ci->uri->segment($this->config['uri_segment']+1);
		$cur_page = $cur_page <= 0 ? 1 : $cur_page;
		return $cur_page;
	}

	function _prepare_rows(&$rows)
	{
		$new_rows = array();

		foreach($rows as $row)
		{
			$new_row = array();

			$primary_key_value = isset($row[ $this->config['primary_key'] ]) ? $row[ $this->config['primary_key'] ] : null;

			if ($primary_key_value && ($this->config['edit'] || $this->config['delete']))
			{
				$cur_page = $this->_current_page();
				$suffix = $cur_page > 1 ? ('/listing/'.$cur_page) : '';

				$actions = '';
				if ($this->config['edit'])
				{
					$actions .= anchor( $this->_base_url().'edit/'.$primary_key_value.$suffix, $this->config['labels']['edit'], 'class="edit"');
				}
				if ($this->config['delete'])
				{
					$actions .= $actions != '' ? ' ' : '';
					$actions .= anchor( $this->_base_url().'delete/'.$primary_key_value.$suffix, $this->config['labels']['delete'], 'class="delete"');
				}

				$new_row['actions'] = $actions;
			}

			foreach($this->config['fields'] as $fieldname => $definition)
			{
				if (is_numeric($fieldname) && isset($definition['name']))
				{
					$fieldname = $definition['name'];
				}

				$value = isset($row[$fieldname]) ? $row[$fieldname] : '';
				$value = call_user_func( $this->config['render_cell'], $fieldname, $value, $this);
				$new_row[$fieldname] = $value;
			}
			$new_rows[] = $new_row;
		}
		$rows = $new_rows;
	}

	function _prepare_heading(&$heading)
	{
		$sort_name = $this->_sort_name();
		$sort_dir  = $this->_sort_dir();

		$heading = array_keys($this->config['fields']);
		
		if ($this->config['sorting'] && isset($this->ci->session))
		{
			foreach($heading as &$label)
			{
				if ($sort_name == $label)
				{
					$label = anchor( $this->_base_url().'sort/'.$label.'/'.($sort_dir == 'desc' ? 'asc' : 'desc'), _T($label), 'class="sort-active sort-'.$sort_dir.'"');
				}
				else
				{
					//not active... always asc
					$label = anchor( $this->_base_url().'sort/'.$label, _T($label));
				}
			}
		}
		if ($this->config['edit'] || $this->config['delete'])
		{
   			array_unshift($heading, $this->config['labels']['actions']);
		}
	}

	function _form($id = null)
	{
		$result = '';
		$this->ci->load->helper( array('form', 'inflector') );
		$this->ci->load->library('form_validation');

		foreach($this->config['fields'] as $name=>$definition)
		{
			if (trim($definition['rules']) != '')
			{
				$this->ci->form_validation->set_rules($name, $definition['label'], $definition['rules']);
			}
		}

		$suffix = '';
		if ($this->ci->uri->segment($this->config['uri_segment']+2) == 'listing')
		{
			$page = (int)$this->ci->uri->segment($this->config['uri_segment']+3);
			if ($page>1)
			{
				$suffix = 'listing/'.$page;
			}
		}

		if ($this->ci->form_validation->run())
		{
			$data = array();
			foreach($this->config['fields'] as $name=>$definition)
			{
				$value = $this->ci->input->post($name);
				if ($value !== FALSE)
				{
					$data[$name] = $value;
				}
			}

			if ($id !== NULL)
			{
				if ($this->ci->db->update($this->config['table'], $data, array($this->config['primary_key'] => $id) ))
				{
					$this->add_message('Item updated', 'success', TRUE);
     				redirect( $this->_base_url() . $suffix );
				}
			}
			else
			{
				if ($this->ci->db->insert($this->config['table'], $data))
				{
					$this->add_message('Item created', 'success', TRUE);
     				redirect( $this->_base_url() . $suffix );
				}
			}
		}
		else
		{
			$errors = (array)$this->ci->form_validation->error_array();
			foreach($errors as $error)
			{
				$this->add_message($error, 'error');
			}			
		}

		$values = array();
		if ($id)
		{
			$query = $this->ci->db->where($this->config['primary_key'], $id)->get($this->config['table']);
			if ($query->num_rows() > 0)
			{
				$values = $query->row_array();
			}
		}

		$values = is_array($values) ? $values : array();

  		$post = $this->ci->input->post(NULL);
  		if (is_array($post))
  		{
  			foreach($post as $key=>$value)
  			{
  				if (isset($values[$key]))
  				{
  					$values[$key] = $value;
  				}
  			}
  		}
		
		$result .= $this->_show_messages();

  		if ($this->config['form_open'])
  		{
  			$result .= form_open(null, $this->config['form_attr']);
  		}

		foreach($this->config['fields'] as $name=>$definition)
		{
			$extra = sprintf('id="%s"', $definition['name']);
			$render_function = 'form_input';
			$value = isset($values[$name]) ? $values[$name] : $definition['value'];
			if (function_exists('form_'.$definition['type']))
			{
				$render_function = 'form_'.$definition['type'];
			}

			switch($definition['type'])
			{
				case 'hidden':
					$result .= form_hidden($definition['name'], $value);
					break;
				case 'select':
				case 'dropdown':
					$options = isset($definition['options']) ? $definition['options'] : array();
					$result .= $this->config['templates']['form']['item_open'];
					$result .= form_label( $definition['label'] . (strpos($definition['rules'], 'required') !== FALSE ? '<span class="required">*</span> ' : ''), $definition['name']);
					$result .= form_dropdown( $definition['name'], $options, $value, $extra);
					$result .= $this->config['templates']['form']['item_close'];
					break;
				default:
					$result .= $this->config['templates']['form']['item_open'];
					$result .= form_label( $definition['label'] . (strpos($definition['rules'], 'required') !== FALSE ? '<span class="required">*</span> ' : ''), $definition['name']);
					$result .= $render_function($definition['name'], $value, $extra);
					$result .= $this->config['templates']['form']['item_close'];
					break;
			}
		}

		if ($this->config['show_actions'])
		{
			$result .= '
			<div class="form-actions">
				'.anchor( $this->_base_url().$suffix, _T($this->config['labels']['cancel']), 'class="btn"' ).'
				'.form_submit('flame-action', _T( $id === null ? $this->config['labels']['create'] : $this->config['labels']['save']), 'class="btn btn-primary"').'
			</div>
			';
		}

		if ($this->config['form_close'])
  		{
  			$result .= form_close();
  		}

  		if ($this->config['page_title'])
		{
			if ($this->config['page_title']===TRUE)
			{
				$title = $id === null ? _T($this->config['labels']['create']) : _T($this->config['labels']['edit']);
				$title .= ' ' . _T(singular( humanize($this->config['table'])));
			}
			else
			{
				$title = $this->config['page_title'];
			}
			$result = '<div class="page-header">' . heading($title, 1) . '</div>' . $result . '</div>';
		}

		return $result;
	}
	
	function _show_messages()
	{
		$result = '';
		foreach($this->messages as $message)
		{
			$type    = $message['type'];
			$message = $message['message'];
			$result .= str_replace('{type}', $type, $this->config['templates']['message']['message_open']);
			$result .= $message;
			$result .= str_replace('{type}', $type, $this->config['templates']['message']['message_close']);
		}
		return $result;
	}


	function _translate_fields()
	{
		$primary_key = null;
		$fields = array();

		if (empty($this->config['table']))
		{
			return;
		}

		$query  = $this->ci->db->query('DESCRIBE '. $this->config['table']);
		foreach($query->result_array() as $row)
		{
			$definition = array(
				'name'  => $row['Field'],
				'label' => _T(humanize($row['Field'])),
				'type'  => 'text',
				'value' => $row['Default'],
				'rules' => '',
				'db'    => $row,
			);

			if (stripos($row['Type'], 'text') !== FALSE)
			{
    			$definition['type'] = 'textarea';
			}
			if (stripos($row['Field'], 'password') !== FALSE)
			{
				$definition['type'] = 'password';
			}
			if ($row['Null'] == 'NO')
			{
				$definition['rules'] = 'required';
			}

			if (empty($primary_key))
			{
				if ($row['Key'] == 'PRI')
				{
					$primary_key = $row['Field'];
				}
			}
			if (trim($row['Key']))
			{
				$definition['type'] = 'hidden';
				$definition['rules'] = '';
			}

			$fields[ $row['Field'] ] = $definition;
		}
		if (empty($this->config['fields']))
		{
			$this->config['fields'] = $fields;
		}
		else
		{
			if (is_string($this->config['fields']))
			{
				$this->config['fields'] = explode(',', $this->config['fields']);
				array_walk($this->config['fields'], 'trim');
			}
			if (is_array($this->config['fields']))
			{
				$newfields = array();
				foreach($this->config['fields'] as $fieldname)
				{
					if (isset($fields[$fieldname]))
					{
						$newfields[$fieldname] = $fields[$fieldname];
					}
				}
				$fields = $newfields;
				$this->config['fields'] = $fields;
			}
		}

		if (empty($this->config['primary_key']))
		{
			$this->config['primary_key'] = $primary_key;
		}
	}

	function _base_url()
	{
		return rtrim(site_url($this->ci->router->fetch_class().'/'.$this->ci->router->fetch_method()), '/').'/';
	}

	function _sort_name()
	{		
		return isset($this->ci->session) ? $this->ci->session->userdata($this->_base_url().'sort_name') : FALSE;
	}
	function _set_sort_name($name)
	{
		if (isset($this->ci->session))
		{
			$this->ci->session->set_userdata($this->_base_url().'sort_name', $name);
		}
	}
	function _sort_dir()
	{
		return isset($this->ci->session) ? $this->ci->session->userdata($this->_base_url().'sort_dir') : FALSE;
	}
	function _set_sort_dir($dir)
	{
		if (isset($this->ci->session))
		{
			$this->ci->session->set_userdata($this->_base_url().'sort_dir', $dir);
		}
	}
}