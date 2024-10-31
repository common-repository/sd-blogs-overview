<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Blogs Overview
Plugin URI: http://it.sverigedemokraterna.se
Description: Provides an overview of all of the blog settings in the network.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: http://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
License: GPLv3
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	SD Blogs Overview 
	
	@brief		Provides an overview of all of the blog settings in the network.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
require_once( 'SD_Blogs_Overview_Base.php' );
class SD_Blogs_Overview
	extends SD_Blogs_Overview_Base
{
	/**
		Local options.
		
		- @b	collect_transients		Should transient options (beginning with an underscore) be collected also?
		- @b	overview_data			Overview class with blogs data.
		- @b	max_option_length		Maximum option length to collect.
		- @b 	visible_options			Array of options (columns) that are visible in the overview.
		
		@var	$local_options
	**/
	protected $site_options = array(
		'collect_transients' => false,
		'max_option_length' => 512,
		'overview_data' => false,
		'visible_options' => array( 'siteurl' ),
	);

	public function __construct()
	{
		parent::__construct( __FILE__ );

		add_action( 'admin_menu',									array( $this, 'admin_menu') );
		add_action( 'network_admin_menu',							array( $this, 'admin_menu') );
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------
	
	public function admin_menu()
	{
		if ( is_super_admin() )
		{
			$this->load_language();
			add_submenu_page(
				'index.php',
				$this->_('SD Blogs overview'),
				$this->_('SD Blogs overview'),
				'read',
				'sd_blogs_overview',
				array( &$this, 'admin' )
			);
		}
	}

	public function admin()
	{
		$tab_data = array(
			'tabs'		=>	array(),
			'functions' =>	array(),
		);
				
		$tab_data['default'] = 'sessions_overview';

		$tab_data['tabs']['overview'] = $this->_( 'Overview' );
		$tab_data['functions']['overview'] = 'admin_overview';

		$tab_data['tabs']['admin_settings'] = $this->_( 'Settings' );
		$tab_data['functions']['admin_settings'] = 'admin_settings';
		
		$tab_data['tabs']['admin_uninstall'] = $this->_( 'Uninstall' );
		$tab_data['functions']['admin_uninstall'] = 'admin_uninstall';

		$this->tabs($tab_data);
	}
	
	/**
		@brief	The overview.
	**/
	public function admin_overview()
	{
		$visible_options = $this->get_site_option( 'visible_options' );
		$editing = false;	// Is the user editing the values?
		$returnValue = '';
		$form = $this->form();
		
		if ( isset( $_POST['collect'] ) )
		{
			$this->collect_data();
			$this->message( $this->_( 'The data has been collected from all the blogs!' ) );
		}
		
		if ( isset( $_POST['save'] ) && isset( $_POST['checks'] ) )
		{
			foreach( $_POST['checks'] as $blog_id => $ignore )
			{
				switch_to_blog( $blog_id );
				foreach( $visible_options as $option )
				{
					$value = $_POST['values'][$blog_id][$option];
					if ( $value == '' && isset( $_POST['delete_empty'] ) )
						delete_option( $option );
					else
						update_option( $option, $value );
				}
				restore_current_blog();
			}
			$this->message( $this->_( 'The options have been set on the selected blogs.' ) );
		} // Edit
		
		if ( isset( $_POST['action_submit'] ) && isset( $_POST['checks'] ) )
		{
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['checks'] as $blog_id => $ignore )
				{
					switch_to_blog( $blog_id );
					
					foreach( $visible_options as $option )
						delete_option( $option );
					restore_current_blog();
				}
				foreach( $visible_options as $option )
					$visible_options = $this->remove_visible_option( $option );
				$this->message( $this->_( 'The visible options have been deleted from the selected blogs.' ) );
			}	// delete

			if ( $_POST['action'] == 'edit' )
			{
				$editing = true;
			}
		}
		
		$data = $this->get_site_option( 'overview_data' );
		if ( $data === false )
		{
			$data = $this->collect_data();
		}
		
		if ( ! $editing )
		{
			$input_collect = array(
				'name' => 'collect',
				'type' => 'submit',
				'value' => $this->_( 'Collect data' ),
				'css_class' => 'button-primary',
			);
			
			echo $form->start();
			echo '<p>' . $form->make_input( $input_collect ) . '</p>';
			echo $form->stop();
		}
		
		$t_body = '';
		
		$blogs_to_show = $data->which_blogs_have( $visible_options );
		
		foreach( $blogs_to_show as $blog_id )
		{
			$check = array(
				'type' => 'checkbox',
				'checked' => isset( $_POST['checks'][ $blog_id ] ),
				'label' => $blog_id,
				'name' => $blog_id,
				'nameprefix' => '[checks]',
			);
			
			$blog_name = '<a href="' . $data->get( $blog_id, 'siteurl' ) . '">' . $data->get( $blog_id, 'blogname' ) . '</a>';
			$blog_name .= ' <small><a href="' . $data->get( $blog_id, 'siteurl' ) . '/wp-admin" title="' .  $this->_( 'Panel' ) . '">[' .  $this->_( 'P' ) . ']</a></small>';
			$row = '<tr blog_id="'.$blog_id.'">';
			$row .= '<th scope="row" class="check-column">' . $form->make_input( $check ) . ' <span class="screen-reader-text">' . $form->make_label( $check ) . '</span></th>';
			$row .= '<td>' . $blog_name . '</td>';
			foreach( $visible_options as $option )
			{ 
				if (
					! $editing
					||
					( $editing && ! isset( $_POST['checks'][ $blog_id ] ) )		// Is editing, but this blog wasn't selected for editing.
				)
					$row .= '<td class="'.$option.'" option="'.$option.'">' . htmlspecialchars( $data->get( $blog_id, $option ) ) . '</td>';
				else
				{
					$input_option = array(
						'name' => $option,
						'type' => 'text',
						'size' => 30,
						'label' => $option,
						'value' => htmlspecialchars( $data->get( $blog_id, $option ) ),
						'nameprefix' => '[values]['.$blog_id.']',
					);
					$row .= '<td class="'.$option.'" option="'.$option.'">
						<span class="screen-reader-text">' . $form->make_label( $input_option ) . '</span>
						' . $form->make_input( $input_option ) . '
					</td>';
				}
			}
			$row .= '</tr>';
			$t_body .= $row;
		}
		
		$headers = '';
		foreach( $visible_options as $option ) 
			$headers .= '<th>' . $option . '</th>';
			
		$check = array(
			'type' => 'checkbox',
			'checked' => false,
			'name' => 'checked',
		);
		
		$overview_data_collected = $this->get_site_option( 'overview_data_collected' );
		$returnValue .= $form->start() . '
			<p>
				' . sprintf(
					$this->_( 'Last update: <span title="%s">%s</span>' ),
						$data->get_collected(),
						$this->ago( $data->get_collected() )
					) . '
			</p>
		';
		
		if ( ! $editing )
		{
			$input_actions = array(
				'type' => 'select',
				'name' => 'action',
				'label' => $this->_('With the selected rows'),
				'options' => array(
					array( 'value' => '', 'text' => $this->_('Do nothing') ),
					array( 'value' => 'delete', 'text' => $this->_('Delete visible options') ),
					array( 'value' => 'edit', 'text' => $this->_('Edit') ),
				),
			);
			
			$input_action_submit = array(
				'type' => 'submit',
				'name' => 'action_submit',
				'value' => $this->_('Apply'),
				'css_class' => 'button-secondary',
			);
			
			$returnValue .= '
				<div>
					' . $form->make_label( $input_actions ) . '
					' . $form->make_input( $input_actions ) . '
					' . $form->make_input( $input_action_submit ) . '
				</div>
			';
		}
		else
		{
			$input_delete_empty = array(
				'name' => 'delete_empty',
				'type' => 'checkbox',
				'label' => $this->_( 'Delete empty options' ),
				'description' => $this->_( 'If an option is left empty, delete the option completely from the selected blog?' ),
			);
			
			$returnValue .= $this->display_form_table( array(
				'inputs' => array(
					$input_delete_empty
				)
			) );

			$input_save = array(
				'name' => 'save',
				'type' => 'submit',
				'value' => $this->_( 'Save' ),
				'css_class' => 'button-primary',
			);
			
			$returnValue .= '<p>' . $form->make_input( $input_save ) . '</p>';
		}
		
		$returnValue .= '
			<table class="widefat sd_blogs_overview' . ( $editing ? ' editing' : '' ) . '">
				<thead>
					<tr>
						<th class="check-column">' . $form->make_input( $check ) . '<span class="screen-reader-text">' . $this->_('Selected') . '</span></th>
						<th>' . $this->_( 'Blog' ) . '</th>
						' . $headers . '
					</tr>
				</thead>
				<tbody>
					' . $t_body . '
				</tbody>
			</table>
			' . $form->stop() . '
		';
		
		if ( $editing )
		{
			$returnValue .= '
				<h3>' . $this->_( 'Javascript tools' ) . '</h3>
				
				<p>' . $this->_( 'The following buttons use javascript to help edit values.' ) . '</p>
			';
			
			// Search and replace
			$inputs = array(
				'this_text' => array(
					'name' => 'this_text',
					'type' => 'text',
					'size' => '20',
					'maxlength' => '1024',
					'label' => $this->_( 'Replace this text' ),
					'validation' => array( 'empty' => true ),
				),
				'with_this' => array(
					'name' => 'with_this',
					'type' => 'text',
					'size' => '20',
					'maxlength' => '1024',
					'label' => $this->_( 'With this text' ),
					'validation' => array( 'empty' => true ),
				),
				'replace' => array(
					'name' => 'replace',
					'type' => 'submit',
					'value' => $this->_( 'Replace' ),
					'css_class' => 'button-secondary',
				),
			);
				
			$returnValue .= '
				<h4>' . $this->_( 'Search and replace' ) . '</h4>
				
				<p>' . $this->_( 'Replaces the text in the selected options.' ) . '</p>

				' . $this->display_form_table( array( 'inputs' => $inputs ) ) . '

			';
		}
		
		wp_enqueue_script( 'jquery-ui' );
		$returnValue .= '
			<script type="text/javascript" src="'. $this->paths["url"] . "/js/jquery.tablesorter.min.js" .'"></script>
			<script type="text/javascript">
				var sd_blogs_overview_strings = {
					"copy_to_all" : "' . $this->_( 'Copy to all marked blogs' ) . '",
					"copy_to_all_title" : "' . $this->_( 'Copies this value to all of the selected blogs' ) . '"
				};
				
				jQuery(document).ready(function($)
				{
					$("table.sd_blogs_overview").tablesorter({
						headers: { 0: { sorter: false} }
					});
					
				});
			</script>
			<script type="text/javascript" src="'. $this->paths["url"] . "/js/sd_blogs_overview.js" .'"></script>
		';
		echo $returnValue;
	}
	/**
		@brief	Configure global Overview settings.
	**/
	public function admin_settings()
	{
		$form = $this->form();
		
		if ( isset( $_POST['update'] ) )
		{
			if ( !isset( $_POST['visible_options'] ) )
				$_POST['visible_options'] = array();
			$this->update_site_option( 'visible_options', $_POST['visible_options'] );
			$this->update_site_option( 'collect_transients', isset( $_POST['collect_transients'] ) );
			$this->update_site_option( 'max_option_length', intval( $_POST['max_option_length'] ) );
			$this->message( $this->_( 'The settings have been updated!' ) );
		}
		
		$data = $this->get_site_option( 'overview_data' );
		
		$inputs = array(
			'collect_transients' => array(
				'name' => 'collect_transients',
				'type' => 'checkbox',
				'label' => $this->_( 'Collect transients' ),
				'description' => $this->_( 'Also store transient options that start with underscores.' ),
				'checked' => $this->get_site_option( 'collect_transients' ),
			),
			'max_option_length' => array(
				'name' => 'max_option_length',
				'type' => 'text',
				'size' => 5,
				'label' => $this->_( 'Maximum option length' ),
				'description' => $this->_( 'Options with strings longer than this value are ignored.' ),
				'value' => $this->get_site_option( 'max_option_length' ),
			),
			'visible_options' => array(
				'name' => 'visible_options',
				'type' => 'select',
				'label' => $this->_( 'Visible options' ),
				'description' => $this->_( 'Which options to show in the overview.' ),
				'value' => $this->get_site_option( 'visible_options' ),
				'css_style' => 'height: auto;',
				'options' => array_combine( array_keys( $data->keys ), array_keys( $data->keys ) ),
				'size' => 30,
				'multiple' => true,
			),
			'update' => array(
				'name' => 'update',
				'type' => 'submit',
				'value' => $this->_( 'Update settings' ),
				'css_class' => 'button-primary',
			),
		);
		
		$returnValue = $form->start();
		$returnValue .= $this->display_form_table( array(
			'inputs' => array(
				$inputs['collect_transients'],
				$inputs['max_option_length'],
				$inputs['visible_options'],
			),
		) );
		
		$returnValue .= $form->make_input( $inputs['update'] );

		$returnValue .= $form->stop();

		echo $returnValue;
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------

	/**
		Goes through all the blogs and collects their domain and CPD data.
		
		@return		The collected SD_Blogs_Overview_Data object.
	**/
	public function collect_data()
	{
		$blogs = $this->get_all_blogs();
		$data = new SD_Blogs_Overview_Data();
		
		$collect_transients = $this->get_site_option( 'collect_transients' );
		
		$max_option_length = $this->get_site_option( 'max_option_length' );
		
		$counter = 0;
		
		foreach( $blogs as $blog )
		{
			$blog_id = $blog->blog_id;
			switch_to_blog( $blog_id );
			$options = wp_load_alloptions();
			ksort( $options );
			foreach( $options as $key => $value )
			{
				// Skip transient stuff?
				if ( !$collect_transients && ( substr( $key, 0, 1 ) == '_' ) )
					continue;
				// Too beacoup?
				if ( strlen( $value ) > $max_option_length )
					continue;
				$counter += strlen( $value );
				$data->set( $blog_id, $key, $value );
			}
			restore_current_blog();
		}
		$data->collected();
		$this->update_site_option( 'overview_data', $data );
		return $data;
	}
	
	/**
		@return		An array of all available blogs.
	**/
	public function get_all_blogs()
	{
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM wp_blogs ORDER BY blog_id" ) );
	}
	
	/**
		Removes an option from the list of visible options.
		
		@param		$option		Option namn to remove.
		@return					The new list of visible options.
	**/
	public function remove_visible_option($option)
	{
		$options = $this->get_site_option( 'visible_options' );
		$options = array_flip( $options );
		unset( $options[ $option ] );
		$options = array_flip( $options );
		$this->update_site_option( 'visible_options', $options );
		return $options;
	}
}
$SD_Blogs_Overview = new SD_Blogs_Overview();

class SD_Blogs_Overview_Data
{
	public $collected = false;
	public $data = array();
	public $keys = array();
	
	/**
		Marks the data as collected.
	**/
	public function collected()
	{
		global $SD_Blogs_Overview;
		ksort( $this->keys );
		$this->collected = $SD_Blogs_Overview->now();
	}
	
	/**
		@return		When the data was last collected. now() timestamp.
	**/
	public function get_collected()
	{
		return $this->collected;
	}
	
	/**
		Returns a value for a key / option name.
		
		@param		$blog_id		Blog ID.
		@param		$key			Key / option name to fetch.
		@return						The option value, or an empty string if the blog doesn't have such an option.
		
	**/
	public function get( $blog_id, $key )
	{
		if ( !isset( $this->data[ $blog_id ][ $key ] ) )
			return '';
		return $this->data[ $blog_id ][ $key ];
	}
	
	/**
		Sets a value for a blog.
		
		@param	$blog_id	Blog ID
		@param	$key		Key / option name
		@param	$value		New value / option value
	**/
	public function set( $blog_id, $key, $value )
	{
		if ( !isset( $this->data[ $blog_id ] ) )
			$this->data[ $blog_id ] = array();
		
		if ( ! isset( $this->keys[ $key ] ) )
			$this->keys[ $key ] = array();
		
		$this->keys[ $key ][ $blog_id ] = $blog_id;
		$this->data[ $blog_id ][ $key ] = $value;
	}
	
	/**
		@brief		Returns a list of blog_id's that have the options specificed in $options.
		$param		$keys		An array of option names.
		@return					An array of blog_id's that all contain values in the options specificied in $keys.
	**/
	public function which_blogs_have( $options )
	{
		$returnValue = array();
		foreach( $options as $option )
		{
			if ( !isset( $this->keys[ $option ] ) )
				continue;
			$returnValue = array_merge( $returnValue, $this->keys[ $option ] );
		}
		$returnValue = array_values( $returnValue );
		$returnValue = array_flip( $returnValue );
		$returnValue = array_flip( $returnValue );
		return $returnValue;
	}
	
}
