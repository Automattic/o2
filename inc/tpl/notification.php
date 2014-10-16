<table>
	<tbody>
		<tr>
			<# if ( 'error' === data.type ) { #>
				<td class="o2-notification-icon">&#xf414;</td>
			<# } #>
			<td class="o2-notification-message">
				<# if ( data.hasLink ) { #>
					<a href="#" class="o2-notification-link">{{{ data.text }}}</a>
				<# } else { #>
					{{{ data.text }}}
				<# } #>
			</td>
			<# if ( data.isSticky && data.isCloseable ) { #>
				<td class="o2-notification-close"><a href="#" class="o2-notification-close">&#xf405;</a></td>
			<# } #>
		</tr>
	</tbody>
</table>
