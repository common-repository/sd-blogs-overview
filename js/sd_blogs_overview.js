jQuery(document).ready(function($)
{
	if ( $('table.sd_blogs_overview').hasClass('editing') )
	{
		// Copy to all function.
		var copy_to_all = '<div class="row-actions">\
		<span title="' + sd_blogs_overview_strings.copy_to_all_title + '">\
			<a class="copy_to_all" href="#">' + sd_blogs_overview_strings.copy_to_all + '</a>\
		</div>';
		$('table.sd_blogs_overview td input').parent().append( copy_to_all );
		
		$('table.sd_blogs_overview a.copy_to_all').click( function(){
			var option = $(this).parentsUntil('td').parent().attr( 'option' );
			var value = $( 'input', $(this).parentsUntil('td').parent() ).val();
			
			$.each( $( 'table.sd_blogs_overview tbody th.check-column input:checked' ), function (index, item)
			{
				var $parent = $(item).parentsUntil('tr').parent();
				$('td.' + option + ' input.text', $parent ).val( value );
			} );
		} );
		
		// Search and replace
		$('#__replace').click( function()
		{
			var this_text = $('#__this_text').val();
			var with_this = $('#__with_this').val();

			$.each( $( 'table.sd_blogs_overview tbody th.check-column input:checked' ), function (index, item)
			{
				var $parent = $(item).parentsUntil('tr').parent();
				var $text = $( 'input.text', $parent );
				var replaced_text = $text.val().replace( this_text, with_this );
				$text.val( replaced_text );
			} );

			return false;
		} );
	}
});
