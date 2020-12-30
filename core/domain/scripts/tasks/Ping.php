<?php

class Net_Gearman_Job_ping extends Task {
	protected $name = 'ping';

	public function process($request, $args) {
		if (!array_key_exists('text', $args)) {
			throw new Net_Gearman_Job_Exception('Invalid/Missing arguments');
		}

		std_log('PONG ' . $args['text']);
	}
}
