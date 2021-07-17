if('undefined' === typeof(bc_cf7)){
    var bc_cf7 = {

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        mobile: function(){
            jQuery(document).on('ready', function(){
                jQuery('input[type="number"]').attr('pattern', '[0-9]*');
            });
        },

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    };
}
