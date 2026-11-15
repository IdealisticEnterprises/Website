<?php

class IdealisticOfficeHistorySchedulersScheduler implements PhpAsyncSerializable
{

    public static function run(): bool
    {
        $result = false;

        try {
            $query = get_sql_query(
                IdealisticOfficeTable::SCHEDULER_TABLE,
                IdealisticOfficeQuery::SCHEDULER_WITH_TEAM_ID,
                array(
                    array("deletion_date", null),
                    null,
                    array("next_date", "IS", null, 0),
                    array("next_date", "<=", IdealisticOfficeReader::getCurrentDate()),
                    null,
                    null,
                    array("month", "IS", null, 0),
                    array("month", "<=", IdealisticOfficeReader::getCurrentDate(null, "m")),
                    null,
                    null,
                    array("day", "IS", null, 0),
                    array("day", "<=", IdealisticOfficeReader::getCurrentDate(null, "d")),
                    null,
                    null,
                    array("hour", "IS", null, 0),
                    array("hour", "<=", IdealisticOfficeReader::getCurrentDate(null, "H")),
                    null,
                    null,
                    array("minute", "IS", null, 0),
                    array("minute", "<=", IdealisticOfficeReader::getCurrentDate(null, "i")),
                    null,
                ),
                array(
                    "DESC",
                    "id"
                ),
                IdealisticOfficeLimit::MAX_PROCESSED_TASKS
            );

            if (!empty($query)) {
                foreach ($query as $row) {
                    $team = new IdealisticOfficeTeam($row->team_id);

                    if (!$team->hasEstablishedAccess()) {
                        continue;
                    }
                    $object = new IdealisticOfficeScheduler(
                        $team,
                        $row->id,
                        $row->account_id,
                        $row->creation_date,
                        $row->creation_time_zone,
                        $row->creation_reason,
                        $row->month,
                        $row->day,
                        $row->hour,
                        $row->minute,
                        $row->next_date,
                        $row->seconds_frequency,
                        $row->history_id,
                        false
                    );
                    $date = $object->getNextDate();

                    if ($object->setNextDate()->isPositiveOutcome()) {
                        try {
                            $user = $object->getCreator();

                            if ($user === null) {
                                continue;
                            }
                            $summary = $object->run();

                            if ($summary->isPositiveOutcome()) {
                                $result = true;
                            } else {
                                $object->setNextDate($date);
                                IdealisticOfficeError::simpleStore(
                                    $summary
                                );
                            }
                        } catch (Throwable $e) {
                            try {
                                $object->setNextDate($date);
                            } catch (Throwable $ignored) {
                            }
                            IdealisticOfficeError::storeThrowable(
                                null,
                                null,
                                $e
                            );
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $result = false;
            IdealisticOfficeError::storeThrowable(
                null,
                null,
                $e
            );
        }
        return $result;
    }

}
