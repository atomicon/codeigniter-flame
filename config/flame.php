<?php

// Standard

// Database
$config['table']       = null; //mandatory
$config['primary_key'] = null;

// Data
$config['fields']      = null;
$config['rows']        = null;

// Segment
$config['uri_segment'] = 3;

// Page
$config['sorting']     = TRUE;
$config['edit']		   = TRUE;
$config['delete']	   = TRUE;

// Form
$config['form_open']   = TRUE;
$config['form_close']  = TRUE;
$config['form_attr']   = 'class="form"';

// Form and Page
$config['page_title']  = TRUE;
$config['show_actions']= TRUE;

// Use global functions? (if action not is found. search for global function)
$config['use_global_functions'] = TRUE;

// Show error messages?
$config['show_messages'] = TRUE;

// Callbacks
$config['render_cell'] = NULL;

$config['before_update'] = NULL;
$config['after_update']  = NULL;

$config['before_insert'] = NULL;
$config['after_insert']  = NULL;

// Labels for buttons etc.
$config['labels'] = array(
	'actions' => '#',
	'edit'    => 'Edit',
	'delete'  => 'Delete',
	'cancel'  => 'Cancel',
	'create'  => 'Create',
	'save'    => 'Save',
);


// Pagination config
$config['pagination'] = array(
	'placement'        => 'bottom',
	'base_url'         => '', //site_url('my_controller/flame');
	'total_rows'       => 0,
	'per_page'         => 10,
	'use_page_numbers' => TRUE,
	'uri_segment'      => 4,
	'cur_tag_open'     => '<li class="active"><a href="#">',
	'cur_tag_close'    => '</a></li>',
	'full_tag_open'    => '<ul>',
	'full_tag_close'   => '</ul>',
	'num_tag_open'     => '<li>',
	'num_tag_close'    => '</li>',
	'prev_tag_open'	   => '<li>',
	'prev_tag_close'   => '</li>',
	'next_tag_open'	   => '<li>',
	'next_tag_close'   => '</li>',
	'first_tag_open'   => '<li>',
	'first_tag_close'  => '</li>',
	'last_tag_open'    => '<li>',
	'last_tag_close'   => '</li>',
);

// Templates for objects
$config['templates'] = array(
	'table' => array(
		'table_open' => '<table class="table table-bordered table-striped">',
	),
	'pagination' => array(
		'pagination_open' => '<div class="pagination">',
		'pagination_close' => '</div>',
	),
	'form' => array(
		'item_open'  => '<div class="control-group">',
  		'item_close' => '</div>',
	),
	'message' => array(
		'message_open' => '<div class="alert alert-{type}"><button class="close" data-dismiss="alert">&times;</button>',
		'message_close' => '</div>',		
	),	
);