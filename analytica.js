jQuery('document').ready(function(){	
	if ( typeof ajax_object === 'undefined' ){
		console.log("false");
	}
	jQuery('body').on(
		'click', '.prev-page,.next-page,.first-page,.last-page', function(event){
			event.preventDefault();
			//url = jQuery(this).attr('href');
			switch(jQuery(this).attr('class')){
				case 'first-page':
				    page_number = 1;
					break;
				case 'last-page':
				    page_number = parseInt(jQuery('#total-pages').html());
					break;
				case 'prev-page':
				if( parseInt(jQuery('#table-paging').html()) == 1){
					page_number = 0;
				} else{
				   page_number = parseInt(jQuery('#table-paging').html())-1;
				}
					break;
				case 'next-page':
				    if( parseInt(jQuery('#table-paging').html()) == parseInt(jQuery('#total-pages').html()) ){
						page_number = 0;
					} else{
				        page_number = parseInt(jQuery('#table-paging').html())+1;
					}
					break; 
                default:
                    page_number = 1;
                    break;
			}
            var request_input = {
				page_number: page_number,
				action: 'get_next_page',
				whatever: ajax_object.we_value
		   };
		  if(page_number != 0){
		   jQuery.get(ajax_object.ajax_url, request_input, function(data){
			    data = JSON.parse(data, function(k,v){
				  return v;
			    });
				
				if( page_number !== 1 ){
				    jQuery('.first').replaceWith('<a class="first-page" href="#"><span class="screen-reader-text">First page</span><span aria-hidden="true">&laquo;</span></a>');
				
					jQuery('.prev').replaceWith('<a class="prev-page" href="#"><span class="screen-reader-text">Previous page</span><span class="tablenav-pages-navspan" aria-hidden="true">&lsaquo;</span></a>');
				} else {
					jQuery('.prev-page').replaceWith('<span class="prev tablenav-pages-navspan" aria-hidden="true">&lsaquo;</span>');
		            jQuery('.first-page').replaceWith('<span class="first" aria-hidden="true">&laquo;</span>');
				}
				
				if( page_number === parseInt(jQuery('#total-pages').html()) ){
					jQuery('.next-page').replaceWith('<span class="next tablenav-pages-navspan" aria-hidden="true">&rsaquo;</span>');
					jQuery('.last-page').replaceWith('<span class="last" aria-hidden="true">&raquo;</span>');
				} else {
					jQuery('.next').replaceWith('<a class="next-page" href="#"><span class="screen-reader-text">Next page</span><span class="tablenav-pages-navspan" aria-hidden="true">&rsaquo;</span></a>');
					
					jQuery('.last').replaceWith('<a class="last-page" href="#"><span class="screen-reader-text">Last page</span><span aria-hidden="true">&raquo;</span></a>');
				}
				
			    //console.log(data);
				posts = data['rows'];
				//console.log(posts.length);
				for( var i = 0; i < posts.length; i++ ){
					jQuery('.report-table tbody tr:eq('+ i +') th.check-column input.select-post').prop('id', 'cb-select-' + posts[i][1] );
					jQuery('.report-table tbody tr:eq('+ i +') th.check-column input.select-post').prop('value', posts[i][1] );
					for( var j = 0; j < 7; j++ ){
						jQuery('.report-table tbody tr:eq('+ i +') td:eq(' + j + ')').prop('id', posts[i][1] );
						jQuery('.report-table tbody tr:eq('+ i +') td:eq(' + j + ')').html(posts[i][j]);
					}
				}
				if( posts.length < 10 ){
					for( var i=posts.length; i<10; i++){
						//jQuery('.report-table tbody tr:eq('+ i +') th.check-column input.select-post').attr('disabled', true );
						//jQuery('.report-table tbody tr:eq('+ i +') th.check-column input.select-post').hide();
						for( var j = 0; j < 7; j++ ){
						    jQuery('.report-table tbody tr:eq('+ i +') td:eq(' + j + ')').html('');
					    }
					}
				}		
				
				
			
				
				jQuery('#table-paging').text(page_number);
		   });
		  } 
	});
	jQuery('#draft').click( function( event ){
		event.preventDefault();
		
		var selectedPosts = [];
		jQuery('input[type="checkbox"]:checked').each(function(){
			//console.log(jQuery('#' + jQuery(this).val()).is('td'));
			//console.log(jQuery('#' + jQuery(this).val() + '> strong > span').is('span'));
			selectedPosts.push(jQuery('#' + jQuery(this).val() + '> strong > span').attr('class'));
		});
		//console.log(selectedPosts);
		var data = {
			selected_posts: selectedPosts,
			action: 'post_status_update',
			whatever: ajax_object.we_value
		};
		jQuery.post(ajax_object.ajax_url, data, function(response){
			//console.log( response );
			response = JSON.parse(response, function(k,v){
				return v;
			});
			//console.log(response);
			var arr = Object.keys(response).map(function(k) { return response[k] });
			for( var i = 0; i < arr.length; i++ ){
				jQuery("#cb-select-" + arr[i] ).attr("disabled", true);
				jQuery('#' + arr[i] + ' strong span.status').html('Draft');
			}
		});
	});
});

function changeStatus(post){
	//console.log(post);
	var selectedPosts = [];
	selectedPosts.push(post);
	//console.log(selectedPosts);
	var data = {
		selected_posts: selectedPosts,
		action: 'post_status_update',
		whatever: ajax_object.we_value
	};
	jQuery.post(ajax_object.ajax_url, data, function(response){
		//console.log( response );
		response = JSON.parse(response, function(k,v){
			return v;
		});
		//console.log(response);
		var arr = Object.keys(response).map(function(k) { return response[k] });
		for( var i = 0; i < arr.length; i++ ){
			jQuery("#cb-select-" + arr[i] ).attr("disabled", true);
			jQuery('#' + arr[i] + ' strong span.status').html('Draft');
		}
	});
}
