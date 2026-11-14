<?php
require_once '/var/www/.structure/library/base/communication.php';

if (is_private_connection()) {
    var_dump("enter");

    if (false) {
        require_once '/var/www/.structure/library/base/utilities.php';
        $t = microtime(true);
        get_keys_from_file("paypal_credentials", 9);
        var_dump((microtime(true) - $t));
    }

    if (false) {
        require_once '/var/www/.structure/library/paypal/init.php';
        var_dump(update_paypal_storage(0, 180, true, true));
    }

    if (false) {
        try {
            require_once '/var/www/.structure/library/account/init.php';
            require_once '/var/www/.structure/library/ai/init.php';
            $query = get_sql_query(
                "idealistic.historyPrompts",
                array("history_value", "id"),
                array(
                    array("history_value_positions", null)
                ),
                false ?
                    array(
                        "DESC",
                        "id"
                    )
                    : null,
                1000
            );

            if (!empty($query)) {
                $embedder = new Account();
                $embedder = $embedder->getEmbeddings();

                foreach ($query as $row) {
                    $embedding = $embedder->objectify(
                        AIModelFamily::OPENAI_EMBEDDING_LARGE,
                        $row->history_value,
                        null,
                        false,
                        false
                    );

                    if ($embedding->isPositiveOutcome()) {
                        $embedding = pack("f*", ...$embedding->getObject()[0]);
                        set_sql_query(
                            "idealistic.historyPrompts",
                            array(
                                "history_value_positions" => $embedding
                            ),
                            array(
                                array("id", $row->id)
                            ),
                            null,
                            1
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            var_dump($e->getMessage());
        }
    }

    if (false) {
        try {
            require_once '/var/www/.structure/library/account/init.php';
            require_once '/var/www/.structure/library/ai/init.php';
            $query = get_sql_query(
                "idealistic.historyUserAttachments",
                array("analyzed_description", "id"),
                array(
                    array("analyzed_description_positions", null),
                    array("analyzed_description", "IS NOT", null)
                ),
                true ?
                    array(
                        "DESC",
                        "id"
                    )
                    : null,
                1000
            );

            if (!empty($query)) {
                $embedder = new Account();
                $embedder = $embedder->getEmbeddings();

                foreach ($query as $row) {
                    $embedding = $embedder->objectify(
                        AIModelFamily::OPENAI_EMBEDDING_LARGE,
                        $row->analyzed_description,
                        null,
                        false,
                        false
                    );

                    if ($embedding->isPositiveOutcome()) {
                        $embedding = pack("f*", ...$embedding->getObject()[0]);
                        set_sql_query(
                            "idealistic.historyUserAttachments",
                            array(
                                "analyzed_description_positions" => $embedding
                            ),
                            array(
                                array("id", $row->id)
                            ),
                            null,
                            1
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            var_dump($e->getMessage());
        }
    }

    if (false) {
        try {
            require_once '/var/www/.structure/library/account/init.php';
            require_once '/var/www/.structure/library/ai/init.php';
            require_once '/var/www/.structure/library/base/vector.php';
            $table = "idealistic.historyLinks";
            $column = "analyzed_description_positions";
            $query = get_sql_query(
                $table,
                array($column, "id"),
                array(
                    array($column, "IS NOT", null)
                )
            );
            $math = new VectorMath();

            if (!empty($query)) {
                foreach ($query as $row) {
                    set_sql_query(
                        $table,
                        array(
                            $column => $math->normalizeBinaryVector($row->{$column})
                        ),
                        array(
                            array("id", $row->id)
                        ),
                        null,
                        1
                    );
                }
            }
        } catch (Throwable $e) {
            var_dump($e->getMessage());
        }
    }

}