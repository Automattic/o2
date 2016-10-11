<div class="entry-content">
	<div class="o2-editor">
		{{{ data.postFormBefore }}}
		<textarea title="" placeholder="{{ data.postPrompt }}" class="o2-editor">{{ data.contentRaw }}</textarea>
		<div class="o2-editor-footer">
			<ul class="o2-editor-tabs">
				<li class="selected"><a href="#" class="o2-editor-edit-button genericon-edit">{{ data.strings.edit }}</a></li>
				<li><a href="#" class="o2-editor-preview-button genericon-show">{{ data.strings.preview }}</a></li>
			</ul>

			<a href="#" class="o2-save primary" title="&#8984;-enter">{{ data.strings.post }}</a>

			<div class="o2-post-form-options">
				{{{ data.postFormExtras }}}
			</div>
		</div>
	</div>
</div>
