(function($) {
    $('.security-list-remove-item').click(function(){
        $('#remove_ip').val( $(this).data('ip') );
        $('#remove_list').val( $(this).data('list') );
        $(this).closest('li').fadeOut(500,function(){ $('#eld-security-form').submit(); });
    });
    
    $('#search_blacklist').click(function(){
        var IP=$('#search_ip_blacklist').val(),
            code = $('#search_blacklist_code'),
            message = code.next(),
            button = $('#delete_search_blacklist');
        
        
        code.removeClass().addClass('dashicons dashicons-search');
        message.text('Vérification en cours');
        button.hide();
        
        $.post(
                ajaxurl,
        {
            'action':'search_ip_blacklist',
            'ip':IP
        },
        function(response){
            
            switch(response.code){
                case 'invalid':
                    code.removeClass().addClass('dashicons dashicons-warning');
                    break;
                case 'missing':
                    code.removeClass().addClass('dashicons dashicons-no');
                    break;
                case 'found':
                    code.removeClass().addClass('dashicons dashicons-yes');
                    button.show();
                    break;
                default:
                    code.removeClass().addClass('dashicons dashicons-search');
                    break;
            }
            message.text(response.message);
        },
        'json'
        )
        
    });
    
    
})(jQuery);