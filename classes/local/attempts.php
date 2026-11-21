<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_contentdesigner\local;

/**
 * Attempts of learners in a Content Designer activity.
 *
 * An attempt groups the progress of a learner (H5P results, answers to questions). Without an addon, every learner
 * has one attempt per activity, created on the first visit. Addons may let learners make more attempts.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempts {
    /**
     * The latest attempt of a user, created if the user has none.
     *
     * Guests share one user account and cannot save progress: they get an attempt that is not stored (id 0), see
     * {@see self::guest_attempt()}.
     *
     * @param int $contentdesignerid The activity instance id.
     * @param int $userid The user.
     * @return \stdClass The record of contentdesigner_attempts.
     */
    public static function get_current(int $contentdesignerid, int $userid): \stdClass {
        if (isguestuser($userid)) {
            return self::guest_attempt($contentdesignerid, $userid);
        }
        return self::get_latest($contentdesignerid, $userid) ?: self::create($contentdesignerid, $userid, true);
    }

    /**
     * The attempt that guests view the content in. It is not stored (id 0), so nothing can be saved in it.
     *
     * @param int $contentdesignerid The activity instance id.
     * @param int $userid The guest user.
     * @return \stdClass An attempt record that is not stored.
     */
    public static function guest_attempt(int $contentdesignerid, int $userid): \stdClass {
        return (object) [
            'id' => 0,
            'contentdesignerid' => $contentdesignerid,
            'userid' => $userid,
            'attempt' => 1,
            'grade' => 0,
            'timestart' => 0,
            'timefinish' => 0,
        ];
    }

    /**
     * The latest attempt of a user.
     *
     * @param int $contentdesignerid The activity instance id.
     * @param int $userid The user.
     * @return \stdClass|null The record of contentdesigner_attempts, null if the user has none.
     */
    public static function get_latest(int $contentdesignerid, int $userid): ?\stdClass {
        global $DB;
        $attempts = $DB->get_records(
            'contentdesigner_attempts',
            ['contentdesignerid' => $contentdesignerid, 'userid' => $userid],
            'attempt DESC, id DESC',
            '*',
            0,
            1
        );
        return $attempts ? reset($attempts) : null;
    }

    /**
     * The attempt to render the content in: the given one, which must belong to the user, or the current one.
     *
     * @param int $contentdesignerid The activity instance id.
     * @param int $userid The user.
     * @param int $attemptid The requested attempt, 0 for the current one.
     * @return \stdClass The record of contentdesigner_attempts.
     */
    public static function get_for_render(int $contentdesignerid, int $userid, int $attemptid): \stdClass {
        if ($attemptid) {
            return access::get_attempt($attemptid, $contentdesignerid, $userid);
        }
        return self::get_current($contentdesignerid, $userid);
    }

    /** @var string Component of the lock under which attempts are created, see {@see self::lock_resource()}. */
    public const LOCK_COMPONENT = 'mod_contentdesigner';

    /**
     * The resource of the lock under which the attempts of a user in an activity are created. Plugins that check
     * conditions before an attempt is created pass them to {@see self::create()} instead of taking an own lock.
     *
     * @param int $contentdesignerid The activity instance id.
     * @param int $userid The user.
     * @return string
     */
    public static function lock_resource(int $contentdesignerid, int $userid): string {
        return "attempt_{$contentdesignerid}_{$userid}";
    }

    /**
     * Creates the next attempt of a user. Guests cannot make attempts.
     *
     * The attempts of a user in an activity are created under one lock, so parallel requests (several tabs,
     * refreshed fragments, double clicks) cannot create two attempts with the same number, nor pass the checks of a
     * plugin twice. The lock is required: if it cannot be obtained, no attempt is created.
     *
     * @param int $contentdesignerid The activity instance id.
     * @param int $userid The user.
     * @param bool $onlyfirst Create the attempt only if the user has none yet, otherwise return the latest one.
     * @param callable|null $check Runs under the lock before the attempt is created, with the attempts of the user
     *     (records of contentdesigner_attempts, oldest first). It may return an existing attempt to use instead of a
     *     new one (e.g. an unfinished attempt), null to create the attempt, or throw to refuse it (e.g. a limit).
     * @return \stdClass The record of contentdesigner_attempts.
     * @throws \moodle_exception locktimeout if the lock cannot be obtained.
     */
    public static function create(
        int $contentdesignerid,
        int $userid,
        bool $onlyfirst = false,
        ?callable $check = null
    ): \stdClass {
        global $DB;

        if (isguestuser($userid)) {
            throw new \moodle_exception('guestnoprogress', 'mod_contentdesigner');
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory(self::LOCK_COMPONENT);
        $lock = $lockfactory->get_lock(self::lock_resource($contentdesignerid, $userid), 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'moodle');
        }
        try {
            $latest = self::get_latest($contentdesignerid, $userid);
            if ($latest && $onlyfirst) {
                return $latest;
            }
            if ($check) {
                $attempts = $DB->get_records(
                    'contentdesigner_attempts',
                    ['contentdesignerid' => $contentdesignerid, 'userid' => $userid],
                    'attempt ASC, id ASC'
                );
                if ($existing = $check($attempts)) {
                    return $existing;
                }
            }
            $attempt = (object) [
                'contentdesignerid' => $contentdesignerid,
                'userid' => $userid,
                'attempt' => $latest ? $latest->attempt + 1 : 1,
                'grade' => 0,
                'timestart' => time(),
                'timefinish' => 0,
            ];
            $attempt->id = $DB->insert_record('contentdesigner_attempts', $attempt);
        } finally {
            $lock->release();
        }
        return $attempt;
    }
}
