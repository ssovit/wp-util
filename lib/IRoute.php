<?php

namespace Sovit\Utilities;
if (!class_exists(__NAMESPACE__."\IRoute")) {

    interface IRoute {
        /**
         * @param \WP_REST_Request $request
         */
        public function callback(\WP_REST_Request $request);

        public function getRestArgs();

        public function getRestEndpoint();

        public function getRestMethod();
    }
}