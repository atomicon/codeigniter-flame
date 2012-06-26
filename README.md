codeigniter-flame
=================

Auto paging and form generation

Currently under heavy development
Todo: documentation codewise and public

How to use in it's basic form
- Create a controller (e.g. Admin)
- In the controller create a function (e.g. Users)
- Create a standard 'users' table in mysql with an id, username, email etc.
- In the initialize function point the table to 'users'

Example code basic example:

	<?php

	class Admin extends CI_Controller
	{

		function users()
		{
			//load the libraries
			$this->load->library('session'); //session for sorting and messages
			$this->load->library('flame'); //the flame library
			//$this->load->spark('flame/1.0.0'); //not ready yet

			$flame  = new Flame(); //instantiate a new flame

			// Set some option you like (see the config for plenty more options)
			$options = array(
				'table' => 'users',
				'fields' => 'id,email,username', //optional: default = all fields
			);

			// Initialize the flame with the options (will be merged with default from config file)
			$flame->initialize($options);

			//Output straight away (if you pass TRUE it will return a rendered HTML block)
			$flame->display(FALSE);
		}
	}