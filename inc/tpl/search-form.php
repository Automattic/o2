<div class="o2-post">
	<p>{{{ data.invitation }}}</p>
	<form method="get" id="searchform" action="{{ o2.options.searchURL }}">
		<div>
			<input type="text" size="18" value="{{{ data.lastQuery }}}" name="s" id="s" />
			<input type="submit" id="searchsubmit" value="{{ data.strings.search }}" class="btn" />
		</div>
	</form>
</div>
