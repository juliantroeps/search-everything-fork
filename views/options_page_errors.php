<div class="error se-error-box">
    <p><?php _e( 'Oops, there are errors in your submit:', 'SearchEverythinh' ); ?></p>
    <ul>
		<?php foreach ( $errors as $field => $message ): ?>
            <li><?php echo sprintf( $message, $fields[ $field ] ); ?></li>
		<?php endforeach; ?>
    </ul>
    <p><?php echo sprintf( __( 'Please go %sgo back%s and check your settings again.', 'SearchEverythinh' ), '<a href="#" class="se-back">', '</a>' ) ?></p>
</div>
