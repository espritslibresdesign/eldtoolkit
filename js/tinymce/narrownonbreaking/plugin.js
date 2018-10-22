/**
 * plugin.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2015 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

/*global tinymce:true */

tinymce.PluginManager.add('narrownonbreaking', function(editor) {

        editor.addCommand('mceNarrowNonBreaking', function() {
		editor.insertContent(
			(editor.plugins.visualchars && editor.plugins.visualchars.state) ?
			'<span class="mce-nnbsp">&#8239;</span>' : '&#8239;'
		);

		editor.dom.setAttrib(editor.dom.select('span.mce-nnbsp'), 'data-mce-bogus', '1');
	});

	editor.addButton('narrownonbreaking', {
		title: 'Espace fine insécable',
		cmd: 'mceNarrowNonBreaking'
	});

	editor.addMenuItem('nonbreaking', {
		text: 'NarrowNonbreaking space',
		cmd: 'mceNarrowNonBreaking',
		context: 'insert'
	});

	
});
