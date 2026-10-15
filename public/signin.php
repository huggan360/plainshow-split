<?php
// Sign in through PlainShow, then back to Split.
require dirname(__DIR__) . '/app/bootstrap.php';
require_identity();
redirect_to('/');
