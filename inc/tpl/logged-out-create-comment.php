<header class="o2-comment-header comment">
	<# if ( o2.options.showAvatars && data.author.avatar ) { #>
	<img src="{{ data.author.avatar }}&amp;s={{ data.avatarSize }}" width="{{ data.avatarSize }}" height="{{ data.avatarSize }}" class="avatar" />
	<# } #>
</header>
<div class="o2-editor o2-logged-out-editor">
	<textarea class="o2-editor">{{ data.contentRaw }}</textarea>
	<div class="o2-editor-signin">
		<p>{{ data.strings.fillDetailsBelow }}</p>
		<input type="text" class="o2-comment-email" placeholder="{{ data.strings.commentEmail }}" name="o2-comment-email" value="{{ data.currentUser.noprivUserEmail }}" />
		<input type="text" class="o2-comment-name" placeholder="{{ data.strings.commentName }}" name="o2-comment-name" value="{{ data.currentUser.noprivUserName }}" />
		<input type="text" class="o2-comment-url" placeholder="{{ data.strings.commentURL }}" name="o2-comment-url" value="{{ data.currentUser.noprivUserURL }}" />
	</div>
	<div class="o2-editor-footer">
		<a href="#" class="o2-comment-save primary" title="&#8984;-enter">{{ data.strings.post }}</a>
		<# if ( data.isNew ) { #>
			<a href="#" class="o2-new-comment-cancel">{{ data.strings.cancel }}</a>
		<# } else { #>
			<a href="#" class="o2-comment-cancel">{{ data.strings.cancel }}</a>
		<# } #>
	</div>
</div>
