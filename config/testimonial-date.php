<?php
acf_add_local_field_group( array(
	'key'                   => 'group_5bdc7ab410b92',
	'title'                 => 'Testimonial Date',
	'fields'                => array(
		array(
			'key'               => 'field_5bdc7ac089c59',
			'label'             => 'Date',
			'name'              => 'testimonial_date',
			'type'              => 'date_picker',
			'instructions'      => '',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => array(
				'width' => '',
				'class' => '',
				'id'    => '',
			),
			'display_format'    => 'F j, Y',
			'return_format'     => 'Ymd',
			'first_day'         => 1,
		),
	),
	'location'              => array(
		array(
			array(
				'param'    => 'post_type',
				'operator' => '==',
				'value'    => $post_type,
			),
		),
	),
	'menu_order'            => 0,
	'position'              => 'side',
	'style'                 => 'default',
	'label_placement'       => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen'        => '',
	'active'                => 1,
	'description'           => '',
) );