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

/**
 * Steps definitions related to workshepallocation_manual.
 *
 * @package    workshepallocation_manual
 * @category   test
 * @copyright  2014 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../../lib/behat/behat_base.php');
require_once(__DIR__ . '/../../../../../../lib/behat/behat_field_manager.php');

use Behat\Behat\Context\Step\Given as Given,
    Behat\Gherkin\Node\TableNode as TableNode,
    Behat\Mink\Exception\ElementTextException as ElementTextException;

/**
 * Steps definitions related to workshepallocation_manual.
 *
 * @package    workshepallocation_manual
 * @category   test
 * @copyright  2014 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_workshepallocation_manual extends behat_base {
    /**
     * Manually adds a reviewer for workshep participant.
     *
     * This step should start on manual allocation page.
     *
     * @When /^I add a reviewer "(?P<reviewer_name_string>(?:[^"]|\\")*)" for workshep participant "(?P<participant_name_string>(?:[^"]|\\")*)"$/
     * @param string $reviewername
     * @param string $participantname
     */
    public function i_add_a_reviewer_for_workshep_participant($reviewername, $participantname) {
        $participantnameliteral = $this->getSession()->getSelectorsHandler()->xpathLiteral($participantname);
        $xpathtd = "//table[contains(concat(' ', normalize-space(@class), ' '), ' allocations ')]/".
                "tbody/tr[./td[contains(concat(' ', normalize-space(@class), ' '), ' peer ')]".
                "[contains(.,$participantnameliteral)]]/".
                "td[contains(concat(' ', normalize-space(@class), ' '), ' reviewedby ')]";
        $xpathselect = $xpathtd . "/descendant::select";
        try {
            $selectnode = $this->find('xpath', $xpathselect);
        } catch (Exception $ex) {
            $this->find_button(get_string('showallparticipants', 'workshepallocation_manual'))->press();
            $selectnode = $this->find('xpath', $xpathselect);
        }

        $selectformfield = behat_field_manager::get_form_field($selectnode, $this->getSession());
        $selectformfield->set_value($reviewername);

        if (!$this->running_javascript()) {
            // Without Javascript we need to press the "Go" button.
            $go = $this->getSession()->getSelectorsHandler()->xpathLiteral(get_string('go'));
            $this->find('xpath', $xpathtd."/descendant::input[@value=$go]")->click();
        } else {
            // With Javascript we just wait for the page to reload.
            $this->getSession()->wait(self::EXTENDED_TIMEOUT, self::PAGE_READY_JS);
        }
        // Check the success string to appear.
        $allocatedtext = $this->getSession()->getSelectorsHandler()->xpathLiteral(
            get_string('allocationadded', 'workshepallocation_manual'));
        $this->find('xpath', "//*[contains(.,$allocatedtext)]");
    }

    /**
     * Manually allocates multiple reviewers in workshep.
     *
     * @When /^I allocate submissions in workshep "(?P<workshep_name_string>(?:[^"]|\\")*)" as:"$/
     * @param string $workshepname
     * @param TableNode $table should have one column with title 'Reviewer' and another with title 'Participant' (or 'Reviewee')
     */
    public function i_allocate_submissions_in_workshep_as($workshepname, TableNode $table) {

        $this->find_link($workshepname)->click();
        $this->find_link(get_string('allocate', 'workshep'))->click();
        $rows = $table->getRows();
        $reviewer = $participant = null;
        for ($i = 0; $i < count($rows[0]); $i++) {
            if (strtolower($rows[0][$i]) === 'reviewer') {
                $reviewer = $i;
            } else if (strtolower($rows[0][$i]) === 'reviewee' || strtolower($rows[0][$i]) === 'participant') {
                $participant = $i;
            } else {
                throw new ElementTextException('Unrecognised column "'.$rows[0][$i].'"', $this->getSession());
            }
        }
        if ($reviewer === null) {
            throw new ElementTextException('Column "Reviewer" could not be located', $this->getSession());
        }
        if ($participant === null) {
            throw new ElementTextException('Neither "Participant" nor "Reviewee" column could be located', $this->getSession());
        }
        for ($i = 1; $i < count($rows); $i++) {
            $this->i_add_a_reviewer_for_workshep_participant($rows[$i][$reviewer], $rows[$i][$participant]);
        }
    }
}
