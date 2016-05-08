jQuery( document ).ready(function()
{
	jQuery( '#wp-admin-bar-autoptimize-default li' ).click(function(e)
	{
		var id = ( typeof e.target.id != 'undefined' && e.target.id ) ? e.target.id : jQuery( e.target ).parent( 'li' ).attr( 'id' );
		var action = '';

		if( id == 'wp-admin-bar-autoptimize-delete-cache' ){
			action = 'autoptimize_delete_cache';
		} else {
			return;
		}

		jQuery( '#wp-admin-bar-autoptimize' ).removeClass( 'hover' );

		var modal_loading = jQuery( '<div class="autoptimize-loading"></div>' ).appendTo( 'body' ).show();

		jQuery.ajax({
			type	: 'GET',
			url	: autoptimize_ajax_object.ajaxurl,
			data	: {'action':action},
			dataType: 'json',
			cache	: false, 
			success	: function( data )
			{
				modal_loading.remove();

				jQuery( '#wp-admin-bar-autoptimize-cache-info .size' ).html( '0.00 B' );
				jQuery( '#wp-admin-bar-autoptimize-cache-info .files' ).html( '0' );

				if ( data.title == '' ) return;

				jQuery.Zebra_Dialog( data.desc, {
					'type'   : 'question',
					'title'  : data.title,
					'buttons': [
					{
						caption	: data.yes,
						callback: function()
						{
							var modal_loading = jQuery( '<div class="autoptimize-loading"></div>' ).appendTo( 'body' ).delay( 1500 ).show();

							jQuery.ajax({
								type	: 'GET',
								url	: autoptimize_ajax_object.ajaxurl,
								data	: {'action':'autoptimize_flush_plugins_cache'},
								dataType: 'json',
								cache	: false, 
								success	: function( data )
								{
									modal_loading.remove();

									if ( data.title == '' ) return;

									jQuery.Zebra_Dialog( data.desc, {
										'type'   : 'information',
										'title'  : data.title,
										'buttons': [ data.ok ]
									});
								}
							});
						}
					},{
						caption	: data.no,
						callback: function() {}
					}]
				});
			}
		});
	});
});