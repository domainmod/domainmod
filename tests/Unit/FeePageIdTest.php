<?php
/**
 * /tests/Unit/FeePageIdTest.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
//@formatter:off
namespace DomainMOD\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;


/*
 * The registrar and SSL provider fee pages once read their IDs with (int) ($_POST['x'] ?? 0), which turns a posted
 * array (new_type_id[]=5) into 1 and quietly picks the first SSL type, currency, fee, registrar, or provider. The
 * edit pages also found the fee to save by hidden registrar and TLD (or provider and type) fields rather than its ID,
 * so a changed field edited another fee, and the add pages let the link's TLD or type beat the one in the form. These
 * tests read the page source, since the pages aren't classes.
 */
class FeePageIdTest extends TestCase
{
    public static function pages()
    {
        return array(
            'add registrar fee' => array('assets/add/registrar-fee.php'),
            'edit registrar fee' => array('assets/edit/registrar-fee.php'),
            'add SSL provider fee' => array('assets/add/ssl-provider-fee.php'),
            'edit SSL provider fee' => array('assets/edit/ssl-provider-fee.php'),
            'registrar fee list' => array('assets/registrar-fees.php'),
            'SSL provider fee list' => array('assets/ssl-provider-fees.php'),
        );
    }

    // Each edit page, its fee table, the table its items are in, the request keys it may read, where a missing fee sends
    // the user, the locked row's columns its items are relinked by, and how many updates its save runs
    public static function editPages()
    {
        return array(
            'edit registrar fee' => array('assets/edit/registrar-fee.php', 'fees', 'domains',
                array('fee_id', 'new_currency_id', 'new_initial_fee', 'new_misc_fee', 'new_privacy_fee', 'new_renewal_fee',
                    'new_transfer_fee'),
                '../registrars.php', array('rid' => 'registrar_id', 'new_tld' => 'tld'), 4),
            'edit SSL provider fee' => array('assets/edit/ssl-provider-fee.php', 'ssl_fees', 'ssl_certs',
                array('fee_id', 'new_currency_id', 'new_initial_fee', 'new_misc_fee', 'new_renewal_fee'),
                '../ssl-providers.php', array('sslpid' => 'ssl_provider_id', 'new_type_id' => 'type_id'), 3),
        );
    }

    #[DataProvider('pages')]
    public function testNoIdIsCastFromTheRequestUnchecked($page)
    {
        $this->assertSame(array(), self::uncheckedCasts(self::source($page)));
    }

    // Each page, and the ID variables it reads from the request, with where each one comes from
    public static function idReads()
    {
        return array(
            'add registrar fee' => array('assets/add/registrar-fee.php', array('rid' => array(array('REQUEST', 'rid')))),
            'edit registrar fee' => array('assets/edit/registrar-fee.php', array(
                'fee_id' => array(array('POST', 'fee_id'), array('GET', 'fee_id')),
                'new_currency_id' => array(array('POST', 'new_currency_id')))),
            'add SSL provider fee' => array('assets/add/ssl-provider-fee.php', array(
                'sslpid' => array(array('REQUEST', 'sslpid')),
                'new_type_id' => array(array('POST', 'new_type_id'), array('GET', 'type_id')))),
            'edit SSL provider fee' => array('assets/edit/ssl-provider-fee.php', array(
                'fee_id' => array(array('POST', 'fee_id'), array('GET', 'fee_id')),
                'new_currency_id' => array(array('POST', 'new_currency_id')))),
            'registrar fee list' => array('assets/registrar-fees.php', array(
                'rid' => array(array('GET', 'rid')),
                'export_data' => array(array('GET', 'export_data')))),
            'SSL provider fee list' => array('assets/ssl-provider-fees.php', array(
                'sslpid' => array(array('GET', 'sslpid')),
                'export_data' => array(array('GET', 'export_data')))),
        );
    }

    // Every time an ID is set from the request, it's set through the check, so a cleanup that drops the cast drops
    // the check with it and is caught
    #[DataProvider('idReads')]
    public function testEachIdIsSetThroughTheCheck($page, $ids)
    {
        $source = self::source($page);
        foreach ($ids as $variable => $reads) {
            $this->assertSame(count($reads), preg_match_all('/\$' . $variable . '\s*=(?!=)[^;]*\$_(?:GET|POST|REQUEST)\b/', $source));
            foreach ($reads as $read) {
                $value = '\$_' . $read[0] . '\s*\[\s*[\'"]' . $read[1] . '[\'"]\s*\]';
                $this->assertSame(1, preg_match('/\$' . $variable . '\s*=\s*is_string\(\s*' . $value . '\s*\?\?\s*null\s*\)\s*&&\s*' .
                    'ctype_digit\(\s*' . $value . '\s*\)\s*\?\s*\(int\)\s*' . $value . '\s*:\s*0\s*;/', $source));
            }
        }
    }

    public function testTheCheckRefusesTheOldReads()
    {
        // The reads the pages used before the fix, and other spellings of them, which the check has to refuse
        $this->assertSame(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11), self::uncheckedCasts(
            "\$new_type_id = (int) (\$_POST['new_type_id'] ?? 0);\n" .
            "\$fee_id = (int) \$_REQUEST[\"fee_id\"];\n" .
            // checked, but for another field
            "\$rid = is_string(\$_GET['tld'] ?? null) && ctype_digit(\$_GET['tld']) ? (int) \$_GET['rid'] : 0;\n" .
            // only one of the two checks
            "\$rid = is_string(\$_GET['rid'] ?? null) ? (int) \$_GET['rid'] : 0;\n" .
            "\$rid = ctype_digit(\$_GET['rid']) ? (int) \$_GET['rid'] : 0;\n" .
            // a check turned around
            "\$rid = !is_string(\$_GET['rid'] ?? null) && ctype_digit(\$_GET['rid']) ? (int) \$_GET['rid'] : 0;\n" .
            "\$rid = is_string(\$_GET['rid'] ?? null) && !ctype_digit(\$_GET['rid']) ? (int) \$_GET['rid'] : 0;\n" .
            // the two arms swapped
            "\$rid = is_string(\$_GET['rid'] ?? null) && ctype_digit(\$_GET['rid']) ? 0 : (int) \$_GET['rid'];\n" .
            // the checks in the other order
            "\$rid = ctype_digit(\$_GET['rid']) && is_string(\$_GET['rid'] ?? null) ? (int) \$_GET['rid'] : 0;\n" .
            // turned around with a space after the !
            "\$rid = ! is_string(\$_GET['rid'] ?? null) && ctype_digit(\$_GET['rid']) ? (int) \$_GET['rid'] : 0;\n" .
            // intval(), which turns an array into 1 the same way
            "\$new_type_id = intval(\$_POST['new_type_id'] ?? 0);\n"));
        $this->assertSame(array(), self::uncheckedCasts(
            "\$rid = is_string(\$_GET['rid'] ?? null) && ctype_digit(\$_GET['rid']) ? (int) \$_GET['rid'] : 0;\n" .
            "\$new_currency_id = is_string(\$_POST['new_currency_id'] ?? null) && ctype_digit(\$_POST['new_currency_id'])\n" .
            "    ? (int) \$_POST['new_currency_id'] : 0;\n" .
            // the cast in parentheses
            "\$rid = is_string(\$_GET['rid'] ?? null) && ctype_digit(\$_GET['rid']) ? ((int) \$_GET['rid']) : 0;\n"));
    }

    #[DataProvider('editPages')]
    public function testTheEditPageReadsOnlyTheFeeIdAndItsFields($page, $fee_table, $item_table, $keys)
    {
        $source = self::source($page);

        // Never the whole request, and never $_REQUEST, so a POST without the hidden fee_id is refused even though the
        // form's address still carries one
        $this->assertSame(0, preg_match('/\$_(POST|GET|REQUEST)(?!\s*\[)/', $source));
        $this->assertStringNotContainsString('$_REQUEST', $source);

        preg_match_all('/\$_(?:POST|GET)\s*\[\s*([\'"])([^\'"]+)\1\s*\]/', $source, $matches);
        $read = array_values(array_unique($matches[2]));
        sort($read);
        $this->assertSame($keys, $read);

        // The fee's ID comes from the hidden field on POST and from the link otherwise, never one standing in for the
        // other
        $this->assertSetByMethod($source, 'fee_id', 'fee_id', 'fee_id');
        // and it's never changed after that, so the save and relink use the fee the page was opened for
        $this->assertSame(2, preg_match_all('/\$fee_id\s*=(?!=)/', $source));

        // The CSRF field comes from showFormTop(), so fee_id is the only hidden field the page adds
        preg_match_all('/showInputHidden\(\s*([\'"])([^\'"]+)\1\s*,\s*([^)]*)\)/', $source, $matches);
        $this->assertSame(array('fee_id'), $matches[2]);
        $this->assertSame(array('$fee_id'), array_map('trim', $matches[3]));
    }

    #[DataProvider('editPages')]
    public function testTheEditPageSavesTheFeeByItsIdFromALockedRow($page, $fee_table, $item_table, $keys, $redirect, $columns,
        $update_count)
    {
        $source = self::source($page);

        // The fee is first read by its ID, after the read-only check, and a missing one sends the user back
        $this->assertSame(1, preg_match('/SELECT\s([^"]*)\sFROM\s+' . $fee_table . '\s+WHERE\s+id\s*=\s*:fee_id\s*"/', $source,
            $read, PREG_OFFSET_CAPTURE));
        // with everything the form shows: the registrar and TLD (or provider and type), the fees, and the currency
        $shown = array_values($columns);
        foreach ($keys as $key) {
            if (preg_match('/^new_(\w+_fee)$/', $key, $fee)) $shown[] = $fee[1];
        }
        $shown[] = 'currency_id';
        $selected = array_map('trim', explode(',', $read[1][0]));
        foreach ($shown as $column) {
            $this->assertContains($column, $selected);
        }
        $this->assertSame(1, preg_match('/if\s*\(\s*\$_SERVER\s*\[\s*[\'"]REQUEST_METHOD[\'"]\s*\]\s*==\s*[\'"]POST[\'"]\s*\)\s*\{' .
            '\s*\$system->readOnlyCheck\([^;]*\);\s*\}/', $source, $read_only, PREG_OFFSET_CAPTURE));
        $this->assertLessThan($read[0][1], $read_only[0][1]);
        // on GET and POST alike, so a refused POST still shows the fee's registrar or provider and saved currency
        $this->assertSame(0, self::depthAt($source, $read[0][1]));
        $this->assertBoundByName($source, $read[0][1], array('fee_id'));
        $this->assertSame(1, preg_match('/\$(\w+)\s*=\s*\$stmt->fetch\(\);/', $source, $fetch, PREG_OFFSET_CAPTURE, $read[0][1]));
        $this->assertLessThan($fetch[0][1], self::executeAfter($source, $read[0][1]));
        $this->assertSame(1, preg_match('/if\s*\(\s*!\s*\$' . $fetch[1][0] . '\s*\)\s*\{([^}]*)\}/', $source, $branch,
            PREG_OFFSET_CAPTURE, $read[0][1]));
        // both before the next statement is prepared, so the later locked read can't stand in for them
        $this->assertSame(1, preg_match('/->\s*prepare\s*\(/', $source, $next, PREG_OFFSET_CAPTURE, $read[0][1]));
        $this->assertLessThan($next[0][1], $fetch[0][1]);
        $this->assertLessThan($next[0][1], $branch[0][1]);
        foreach (array(self::executeAfter($source, $read[0][1]), $fetch[0][1], $branch[0][1]) as $offset) {
            $this->assertSame(0, self::depthAt($source, $offset));
        }
        $this->assertStringContainsString("_('That fee no longer exists.')", $branch[1][0]);
        $this->assertMatchesRegularExpression('/header\("Location: ' . preg_quote($redirect, '/') . '"\);\s*exit;\s*$/',
            $branch[1][0]);
        // On GET the form starts with the row's fees and currency
        $this->assertSame(1, preg_match('/\}\s*elseif\s*\(\s*\$_SERVER\s*\[\s*[\'"]REQUEST_METHOD[\'"]\s*\]\s*!=\s*' .
            '[\'"]POST[\'"]\s*\)\s*\{([^}]*)\}/', $source, $get));
        foreach ($shown as $column) {
            if (in_array($column, $columns, true)) continue;
            $field = $column === 'currency_id' ? 'new_currency_id' : 'new_' . $column;
            $this->assertMatchesRegularExpression('/\$' . $field . '\s*=\s*(\(int\)\s*)?\$' . $fetch[1][0] . '->' . $column .
                ';/', $get[1]);
        }

        $begin = strpos($source, '$pdo->beginTransaction();');
        $this->assertNotFalse($begin);

        // The fee's row is locked by its ID inside the transaction
        $this->assertSame(1, preg_match('/SELECT\s([^"]*)\sFROM\s+' . $fee_table . '\s+WHERE\s+id\s*=\s*:fee_id\s+FOR\s+UPDATE\s*"/',
            $source, $lock, PREG_OFFSET_CAPTURE));
        $lock_at = $lock[0][1];
        // reading the columns the items are relinked by
        $selected = array_map('trim', explode(',', $lock[1][0]));
        foreach ($columns as $column) {
            $this->assertContains($column, $selected);
        }
        $this->assertGreaterThan($begin, $lock_at);
        $this->assertBoundByName($source, $lock_at, array('fee_id'));

        // then read into a variable, and a missing row rolls back and leaves before anything is saved
        $this->assertSame(1, preg_match('/\$(\w+)\s*=\s*\$stmt->fetch\(\);/', $source, $fetch, PREG_OFFSET_CAPTURE, $lock_at));
        $this->assertLessThan($fetch[0][1], self::executeAfter($source, $lock_at));
        $locked = $fetch[1][0];
        $this->assertSame(1, preg_match('/if\s*\(\s*!\s*\$' . $locked . '\s*\)\s*\{([^}]*)\}/', $source, $branch,
            PREG_OFFSET_CAPTURE, $lock_at));
        $branch_at = $branch[0][1];
        $this->assertStringContainsString('$pdo->rollback()', $branch[1][0]);
        $this->assertStringContainsString("_('That fee no longer exists.')", $branch[1][0]);
        $this->assertMatchesRegularExpression('/header\("Location: ' . preg_quote($redirect, '/') . '"\);\s*exit;\s*$/',
            $branch[1][0]);

        // The items are relinked by the locked row's registrar and TLD (or provider and type), set after the branch
        $sets = array();
        foreach ($columns as $variable => $column) {
            $this->assertSame(1, preg_match('/\$' . $variable . '\s*=\s*(\(int\)\s*)?\$' . $locked . '->' . $column . ';/', $source,
                $set, PREG_OFFSET_CAPTURE, $branch_at));
            $this->assertGreaterThan($branch_at, $set[0][1]);
            $sets[$variable] = $set[0][1] + strlen($set[0][0]);
        }

        // Every update of the fee table finds the fee by its ID alone, and comes after the branch
        preg_match_all('/UPDATE\s+' . $fee_table . '\s+SET\s[^"]*"/', $source, $updates, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($updates[0]);
        foreach ($updates[0] as $update) {
            $this->assertMatchesRegularExpression('/\sWHERE\s+id\s*=\s*:fee_id\s*"$/', $update[0]);
            $this->assertGreaterThan($branch_at, $update[1]);
            $this->assertBoundByName($source, $update[1], array('fee_id'));
        }

        // and so does the relink, which points the items at that ID
        $this->assertSame(1, preg_match('/UPDATE\s+' . $item_table . '\s+SET\s+fee_id\s*=\s*:fee_id[^"]*"/', $source, $relink,
            PREG_OFFSET_CAPTURE));
        $this->assertGreaterThan($branch_at, $relink[0][1]);
        // after those are set from the locked row, and with nothing changing them in between
        foreach ($sets as $variable => $set_end) {
            $this->assertLessThan($relink[0][1], $set_end);
            $between = substr($source, $set_end, self::executeAfter($source, $relink[0][1]) - $set_end);
            $this->assertSame(0, preg_match_all('/\$' . $variable . '\s*=(?!=)/', $between));
        }
        $where = array();
        foreach ($columns as $variable => $column) {
            $where[] = $column . '\s*=\s*:' . $variable;
        }
        $this->assertMatchesRegularExpression('/\sWHERE\s+' . implode('\s+AND\s+', $where) . '\s*"$/', $relink[0][0]);
        // with each placeholder bound to the variable of the same name
        $this->assertBoundByName($source, $relink[0][1], array_merge(array('fee_id'), array_keys($columns)));

        // The page commits once, after every write has run, so the lock holds until the fee and its items are all
        // saved
        $this->assertSame(1, substr_count($source, '$pdo->commit()'));
        $commit = strpos($source, '$pdo->commit()');
        preg_match_all('/"\s*UPDATE\s/', $source, $writes, PREG_OFFSET_CAPTURE, $begin);
        $this->assertCount($update_count, $writes[0]);
        foreach ($writes[0] as $write) {
            $this->assertGreaterThan(self::executeAfter($source, $write[1]), $commit);
        }
        // The totals are worked out after the relink, so items that had no fee get theirs too
        preg_match_all('/UPDATE\s+' . $item_table . '\s+\w+\s+JOIN\s/', $source, $totals, PREG_OFFSET_CAPTURE);
        $this->assertCount($update_count - 2, $totals[0]);
        foreach ($totals[0] as $total) {
            $this->assertBoundByName($source, $total[1], array('fee_id'));
            // and after the fee itself is saved, so they use its new amounts
            foreach ($updates[0] as $update) {
                $this->assertGreaterThan(self::executeAfter($source, $update[1]), $total[1]);
            }
            $this->assertGreaterThan(self::executeAfter($source, $relink[0][1]), $total[1]);
        }
        $rates = strpos($source, '$conversion->updateRates(');
        $this->assertNotFalse($rates);
        $this->assertGreaterThan($rates, $commit);

        // Once it's committed, the missing fees banner is worked out again, since the relink can link the last item
        // that had no fee
        $flag = $item_table === 'domains' ? 's_missing_domain_fees' : 's_missing_ssl_fees';
        $this->assertSame(1, preg_match('/\$sql\s*=\s*\$queryB->missingFees\(\s*[\'"]' . $item_table . '[\'"]\s*\);\s*' .
            '\$_SESSION\[\s*[\'"]' . $flag . '[\'"]\s*\]\s*=\s*\$system->checkForRows\(\s*\$sql\s*\);/', $source, $refresh,
            PREG_OFFSET_CAPTURE));
        $this->assertGreaterThan($commit, $refresh[0][1]);
        // outside the save's try and catch, so a failure there isn't reported as a failed save
        $this->assertSame(1, preg_match('/\}\s*catch\s*\(\s*Exception\s+\$e\s*\)\s*\{/', $source, $catch, PREG_OFFSET_CAPTURE));
        $this->assertGreaterThan(self::blockEnd($source, $catch[0][1] + strlen($catch[0][0]) - 1), $refresh[0][1]);
        $list = $item_table === 'domains' ? 'registrar-fees' : 'ssl-provider-fees';
        $this->assertSame(1, preg_match('/header\("Location: \.\.\/' . $list . '\.php\?/', $source, $done, PREG_OFFSET_CAPTURE));
        $this->assertLessThan($done[0][1], $refresh[0][1]);
    }

    #[DataProvider('editPages')]
    public function testTheEditPageChecksTheCurrency($page, $fee_table)
    {
        $source = self::source($page);

        $this->assertSame(1, preg_match('/SELECT\s+id\s+FROM\s+currencies\s+WHERE\s+id\s*=\s*:new_currency_id\s*"/', $source, $lookup,
            PREG_OFFSET_CAPTURE));
        $update = strpos($source, 'UPDATE ' . $fee_table);
        $this->assertNotFalse($update);
        $this->assertLessThan($update, $lookup[0][1]);
        $this->assertBoundByName($source, $lookup[0][1], array('new_currency_id'));
        $this->assertRunsOnEveryPost($source, $lookup[0][1]);
        $this->assertNoResetAfter($source, $lookup[0][1]);
        // and the fee is saved with that same currency
        $this->assertBoundByName($source, $update, array('new_currency_id'));
        // and a currency that isn't found is refused
        list($branch, $branch_at) = $this->refusalBranch($source, $lookup[0][1]);
        $this->assertStringContainsString("\$form->addFieldError('new_currency_id', _('Choose the Currency'));", $branch);
        $this->assertStringContainsString('$has_errors = true;', $branch);
        // and the form shows the fee's saved currency again rather than the refused one
        $this->assertMatchesRegularExpression('/\$new_currency_id\s*=\s*\(int\)\s*\$fee_row->currency_id;/', $branch);
        // so nothing is saved: the whole save is inside the block that runs only on a POST with no mistakes
        $this->assertSame(1, preg_match('/if\s*\(\s*\$_SERVER\s*\[\s*[\'"]REQUEST_METHOD[\'"]\s*\]\s*==\s*[\'"]POST[\'"]\s*&&\s*' .
            '!\s*\$has_errors\s*\)\s*\{/', $source, $save, PREG_OFFSET_CAPTURE));
        $this->assertGreaterThan($branch_at, $save[0][1]);
        $this->assertSaveInside($source, $save[0][1] + strlen($save[0][0]) - 1, $fee_table, 'UPDATE');
        // the mistake shows under the menu
        $this->assertStringContainsString("showDropdownBottom('', 'new_currency_id')", $source);
        // A currency that isn't in the list selects an empty option first, so saving again needs one chosen rather
        // than quietly taking the first in the list
        $this->assertSame(1, preg_match('/if\s*\(\s*!\s*in_array\(\s*\$new_currency_id\s*,[^{]*\)\s*\{\s*echo\s+\$form->' .
            'showDropdownOption\(\s*[\'"]{2}\s*,\s*_\([\'"]Choose the Currency[\'"]\)\s*,\s*[\'"]{2}\s*\);\s*\}\s*foreach\s*\(/',
            $source));
        // and each currency option is selected by that same currency
        $this->assertSame(1, preg_match('/echo\s+\$form->showDropdownOption\(\s*\$row->id\s*,[^;]*,\s*\$new_currency_id\s*\);/',
            $source));
    }

    public static function addPageLinkValues()
    {
        return array(
            'add registrar fee' => array('assets/add/registrar-fee.php', 'new_tld', 'tld'),
            'add SSL provider fee' => array('assets/add/ssl-provider-fee.php', 'new_type_id', 'type_id'),
        );
    }

    #[DataProvider('addPageLinkValues')]
    public function testTheAddPageReadsOnlyThePostedValueOnPost($page, $field, $link_key)
    {
        $source = self::source($page);

        // The link's value is read only in the GET arm of the one branch that sets the field
        $this->assertSetByMethod($source, $field, $field, $link_key);

        // and the form shows that value, so a Missing Fees link opens with its TLD filled in or its type chosen
        $shows = $field === 'new_tld'
            ? '/echo\s+\$form->showInputText\(\s*[\'"]new_tld[\'"]\s*,[^;]*,\s*\$unsanitize->text\(\s*\$new_tld\s*\)\s*,/'
            // (?? only falls back when the variable isn't set, which it always is)
            : '/echo\s+\$form->showDropdownOption\(\s*\$row->id\s*,\s*\$row->type\s*,\s*\$new_type_id\s*' .
                '(?:\?\?\s*\$_SESSION\s*\[\s*[\'"]\w+[\'"]\s*\]\s*)?\);/';
        $this->assertSame(1, preg_match($shows, $source));
        if ($field === 'new_type_id') {
            // after an empty first choice, so a type that isn't in the list doesn't quietly become the first one
            $this->assertSame(1, preg_match('/showDropdownTop\(\s*[\'"]new_type_id[\'"][^;]*;\s*' .
                'echo\s+\$form->showDropdownOption\(\s*[\'"]{2}\s*,\s*_\([\'"]Choose an SSL Type[\'"]\)/', $source));
        }
    }

    public function testTheAddPagesLookUpWhatTheFeeIsSavedUnder()
    {
        $source = self::source('assets/add/ssl-provider-fee.php');
        $insert = strpos($source, 'INSERT INTO ssl_fees');
        $this->assertNotFalse($insert);
        // A type that isn't found is refused, and the fee is only added when nothing was refused
        $lookup_at = $this->lookupBefore($source, 'ssl_cert_types', 'new_type_id', $insert);
        $this->assertRunsOnEveryPost($source, $lookup_at);
        $this->assertNoResetAfter($source, $lookup_at);
        list($branch, $branch_at) = $this->refusalBranch($source, $lookup_at);
        $this->assertStringContainsString("\$form->addFieldError('new_type_id', _('Choose the SSL Type'));", $branch);
        $this->assertStringContainsString('$has_errors = true;', $branch);
        $this->assertSame(1, preg_match('/if\s*\(\s*!\s*\$has_errors\s*\)\s*\{/', $source, $save, PREG_OFFSET_CAPTURE, $branch_at));
        $this->assertSaveInside($source, $save[0][1] + strlen($save[0][0]) - 1, 'ssl_fees', 'INSERT INTO');
        // and is added with the type and provider that were checked
        $this->assertBoundByName($source, $insert, array('new_type_id', 'sslpid'));
        $this->assertInsertColumns($source, 'ssl_fees', array('ssl_provider_id' => ':sslpid', 'type_id' => ':new_type_id'));
        // and the certificates of that provider and type are linked to it
        $this->assertAddRelink($source, 'ssl_certs', array('sslpid' => 'ssl_provider_id', 'new_type_id' => 'type_id'));
        // A provider that isn't found sends the user back before anything else, on GET and POST alike
        $lookup_at = $this->lookupBefore($source, 'ssl_providers', 'sslpid', $insert);
        list($branch, $branch_at) = $this->refusalBranch($source, $lookup_at);
        $this->assertSame(0, self::depthAt($source, $lookup_at));
        $this->assertSame(0, self::depthAt($source, $branch_at));
        $this->assertBeforePostHandling($source, $lookup_at, $branch_at);
        $this->assertStringContainsString("_('That SSL provider no longer exists.')", $branch);
        $this->assertMatchesRegularExpression('/header\("Location: \.\.\/ssl-providers\.php"\);\s*exit;\s*$/', $branch);

        $source = self::source('assets/add/registrar-fee.php');
        $insert = strpos($source, 'INSERT INTO fees');
        $this->assertNotFalse($insert);
        $this->assertBoundByName($source, $insert, array('rid', 'new_tld'));
        $this->assertInsertColumns($source, 'fees', array('registrar_id' => ':rid', 'tld' => ':new_tld'));
        $this->assertAddRelink($source, 'domains', array('rid' => 'registrar_id', 'new_tld' => 'tld'));
        $lookup_at = $this->lookupBefore($source, 'registrars', 'rid', $insert);
        list($branch, $branch_at) = $this->refusalBranch($source, $lookup_at);
        $this->assertSame(0, self::depthAt($source, $lookup_at));
        $this->assertSame(0, self::depthAt($source, $branch_at));
        $this->assertBeforePostHandling($source, $lookup_at, $branch_at);
        $this->assertStringContainsString("_('That registrar no longer exists.')", $branch);
        $this->assertMatchesRegularExpression('/header\("Location: \.\.\/registrars\.php"\);\s*exit;\s*$/', $branch);
    }

    // Each fee list, its fee table, the table its owner is in, the owner's ID, the refusal message, and where it sends
    // the user
    public static function feeLists()
    {
        return array(
            'registrar fee list' => array('assets/registrar-fees.php', 'fees', 'registrars', 'rid',
                "_('That registrar no longer exists.')", 'registrars\\.php'),
            'SSL provider fee list' => array('assets/ssl-provider-fees.php', 'ssl_fees', 'ssl_providers', 'sslpid',
                "_('That SSL provider no longer exists.')", 'ssl-providers\\.php'),
        );
    }

    // A registrar or provider that isn't found sends the user back before the fees are read or exported, so a bad ID
    // never lists (or downloads) anything
    #[DataProvider('feeLists')]
    public function testTheFeeListRefusesAnUnknownOwner($page, $fee_table, $table, $parameter, $message, $redirect)
    {
        $source = self::source($page);
        $fees = strpos($source, 'FROM ' . $fee_table . ' AS f');
        $this->assertNotFalse($fees);
        $lookup_at = $this->lookupBefore($source, $table, $parameter, $fees);
        list($branch, $branch_at) = $this->refusalBranch($source, $lookup_at);
        $this->assertSame(0, self::depthAt($source, $lookup_at));
        $this->assertSame(0, self::depthAt($source, $branch_at));
        $this->assertLessThan($fees, $branch_at);
        $this->assertStringContainsString($message, $branch);
        $this->assertMatchesRegularExpression('/header\("Location: ' . $redirect . '"\);\s*exit;\s*$/', $branch);
    }

    // The field is set from the request only in one branch on the request method: from the posted key on POST, and
    // from the link's key otherwise. Each arm reads only its own request array, and every read of either key is inside
    // the branch.
    private function assertSetByMethod($source, $field, $post_key, $get_key)
    {
        $request = '\$_%s\s*\[\s*[\'"]%s[\'"]\s*\]';
        $this->assertSame(1, preg_match('/if\s*\(\s*\$_SERVER\s*\[\s*[\'"]REQUEST_METHOD[\'"]\s*\]\s*==\s*[\'"]POST[\'"]\s*\)\s*\{' .
            '\s*\$' . $field . '\s*=([^;]*' . sprintf($request, 'POST', $post_key) . '[^;]*);' .
            '\s*\}\s*else\s*\{\s*\$' . $field . '\s*=([^;]*' . sprintf($request, 'GET', $get_key) . '[^;]*);\s*\}/',
            $source, $branch));
        $this->assertDoesNotMatchRegularExpression('/\$_(GET|REQUEST)\b/', $branch[1]);
        $this->assertDoesNotMatchRegularExpression('/\$_(POST|REQUEST)\b/', $branch[2]);
        foreach (array_unique(array($post_key, $get_key)) as $key) {
            $reads = '/' . sprintf($request, '(?:GET|POST|REQUEST)', preg_quote($key, '/')) . '/';
            $this->assertSame(preg_match_all($reads, $source), preg_match_all($reads, $branch[1] . ';' . $branch[2]));
        }
        // and the field is set from the request nowhere else
        $this->assertSame(2, preg_match_all('/\$' . $field . '\s*=(?!=)[^;]*\$_(?:GET|POST|REQUEST)\b/', $source));
    }

    // The add page's relink finds the items by the columns, each bound to the variable the fee was added with
    private function assertAddRelink($source, $item_table, $columns)
    {
        $where = array();
        foreach ($columns as $variable => $column) {
            $where[] = $column . '\s*=\s*:' . $variable;
        }
        $this->assertSame(1, preg_match('/UPDATE\s+' . $item_table . '\s+SET\s+fee_id\s*=\s*:new_fee_id[^"]*\sWHERE\s+' .
            implode('\s+AND\s+', $where) . '\s*"/', $source, $relink, PREG_OFFSET_CAPTURE));
        $this->assertBoundByName($source, $relink[0][1], array_keys($columns));
    }

    // The INSERT into the table puts each placeholder in its column
    private function assertInsertColumns($source, $table, $columns)
    {
        $this->assertSame(1, preg_match('/INSERT\s+INTO\s+' . $table . '\s*\(([^)]*)\)\s*VALUES\s*\(([^)]*)\)/', $source, $insert));
        $names = array_map('trim', explode(',', $insert[1]));
        $values = array_map('trim', explode(',', $insert[2]));
        $this->assertCount(count($names), $values);
        $pairs = array_combine($names, $values);
        foreach ($columns as $column => $placeholder) {
            $this->assertSame($placeholder, $pairs[$column] ?? null);
        }
    }

    // How many of the code's blocks are open at the offset (0 at the top level of the page)
    private static function depthAt($source, $offset)
    {
        $depth = 0;
        foreach (\PhpToken::tokenize($source) as $token) {
            if ($token->pos >= $offset) break;
            if (in_array($token->id, array(ord('{'), T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true)) $depth++;
            if ($token->id === ord('}')) $depth--;
        }
        return $depth;
    }

    // Each placeholder of the statement prepared at the offset is bound once, to the variable of the same name
    private function assertBoundByName($source, $offset, $variables)
    {
        $binds = substr($source, $offset, self::executeAfter($source, $offset) - $offset);
        foreach ($variables as $variable) {
            // either spelling of the placeholder, and either way of binding it
            $this->assertSame(1, preg_match_all('/->\s*bind(?:Value|Param)\s*\(\s*[\'"]:?' . $variable . '[\'"]\s*,/', $binds));
            $this->assertMatchesRegularExpression('/\$stmt->bindValue\(\s*[\'"]' . $variable . '[\'"]\s*,\s*\$' . $variable .
                '\s*,/', $binds);
        }
    }

    // The offset of the page's lookup of the parameter's ID in the table, which has to come before the given offset
    private function lookupBefore($source, $table, $parameter, $before)
    {
        $this->assertSame(1, preg_match('/SELECT\s+id\s+FROM\s+' . $table . '\s+WHERE\s+id\s*=\s*:' . $parameter . '\s*"\);\s*' .
            '\$stmt->bindValue\(\s*[\'"]' . $parameter . '[\'"]\s*,\s*\$' . $parameter . '\s*,/', $source, $lookup,
            PREG_OFFSET_CAPTURE));
        $this->assertLessThan($before, $lookup[0][1]);
        $this->assertBoundByName($source, $lookup[0][1], array($parameter));
        return $lookup[0][1];
    }

    // The refusal at the branch offset comes before the page handles a POST (the first POST block after the lookup)
    private function assertBeforePostHandling($source, $lookup_at, $branch_at)
    {
        $this->assertSame(1, preg_match('/if\s*\(\s*\$_SERVER\s*\[\s*[\'"]REQUEST_METHOD[\'"]\s*\]\s*==\s*[\'"]POST[\'"]\s*\)\s*\{/',
            $source, $post, PREG_OFFSET_CAPTURE, $lookup_at));
        $this->assertLessThan($post[0][1], $branch_at);
    }

    // $has_errors starts as false once, before the lookup at the offset, so a refusal there can't be undone later
    private function assertNoResetAfter($source, $offset)
    {
        $this->assertSame(1, preg_match_all('/\$has_errors\s*=\s*false\s*;/', $source, $resets, PREG_OFFSET_CAPTURE));
        $this->assertLessThan($offset, $resets[0][0][1]);
        // and every other time it's set, it's set to true
        $this->assertSame(preg_match_all('/\$has_errors\s*=(?!=)/', $source),
            1 + preg_match_all('/\$has_errors\s*=\s*true\s*;/', $source));
    }

    // The lookup at the offset runs whenever the page is posted: straight inside the POST block, with no other
    // condition around it
    private function assertRunsOnEveryPost($source, $offset)
    {
        $open = self::innermostOpen($source, $offset);
        $this->assertMatchesRegularExpression('/if\s*\(\s*\$_SERVER\s*\[\s*[\'"]REQUEST_METHOD[\'"]\s*\]\s*==\s*' .
            '[\'"]POST[\'"]\s*\)\s*$/', substr($source, 0, $open));
        $this->assertSame(1, self::depthAt($source, $offset));
    }

    // The offset of the brace that opens the innermost block around the offset
    private static function innermostOpen($source, $offset)
    {
        $opens = array();
        foreach (\PhpToken::tokenize($source) as $token) {
            if ($token->pos >= $offset) break;
            if (in_array($token->id, array(ord('{'), T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true)) $opens[] = $token->pos;
            if ($token->id === ord('}')) array_pop($opens);
        }
        if (!$opens) self::fail('Not inside a block');
        return end($opens);
    }

    // The body and offset of the branch that runs when the lookup after the offset finds nothing
    private function refusalBranch($source, $offset)
    {
        $this->assertSame(1, preg_match('/\$(\w+)\s*=\s*\$stmt->fetchColumn\(\)\s*!==\s*false;/', $source, $found,
            PREG_OFFSET_CAPTURE, $offset));
        // the lookup is run before its result is read, in the same block as the branch, and the result is set nowhere
        // else
        $execute = self::executeAfter($source, $offset);
        $this->assertLessThan($found[0][1], $execute);
        $this->assertSame(1, preg_match_all('/\$' . $found[1][0] . '\s*=(?!=)/', $source));
        $this->assertSame(1, preg_match('/if\s*\(\s*!\s*\$' . $found[1][0] . '\s*\)\s*\{([^}]*)\}/', $source, $branch,
            PREG_OFFSET_CAPTURE, $found[0][1]));
        foreach (array($offset, $execute, $found[0][1]) as $step) {
            $this->assertSame(self::depthAt($source, $branch[0][1]), self::depthAt($source, $step));
        }
        return array($branch[1][0], $branch[0][1]);
    }

    // The transaction, every write of the fee table, and the commit are all inside the block opened at the offset
    private function assertSaveInside($source, $open, $fee_table, $write)
    {
        $close = self::blockEnd($source, $open);
        $offsets = array(strpos($source, '$pdo->beginTransaction();'), strpos($source, '$pdo->commit()'));
        $this->assertSame(1, preg_match_all('/' . $write . '\s+' . $fee_table . '\s/', $source, $writes, PREG_OFFSET_CAPTURE));
        $offsets[] = $writes[0][0][1];
        foreach ($offsets as $offset) {
            $this->assertNotFalse($offset);
            $this->assertGreaterThan($open, $offset);
            $this->assertLessThan($close, $offset);
        }
    }

    // The offset of the brace that closes the block opened at the offset, counting only the code's braces (not ones in
    // strings)
    private static function blockEnd($source, $open)
    {
        $depth = 0;
        foreach (\PhpToken::tokenize($source) as $token) {
            if ($token->pos < $open) continue;
            // single-character tokens have the character's code as their id, which text inside a string doesn't
            if (in_array($token->id, array(ord('{'), T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true)) $depth++;
            if ($token->id === ord('}') && --$depth === 0) return $token->pos;
        }
        self::fail('No closing brace');
    }

    // The offset of the execute() that runs the statement prepared at the offset (before the next one is prepared)
    private static function executeAfter($source, $offset)
    {
        $execute = strpos($source, '$stmt->execute();', $offset);
        if ($execute === false) self::fail('No execute() after offset ' . $offset);
        if (preg_match('/->\s*prepare\s*\(/', $source, $next, PREG_OFFSET_CAPTURE, $offset) && $next[0][1] < $execute) {
            self::fail('The statement at offset ' . $offset . ' is never run');
        }
        return $execute;
    }

    // The page's source with each comment blanked out (its line breaks kept, so line numbers still match), so code
    // that's commented out doesn't count
    private static function source($page)
    {
        $source = file_get_contents(DIR_ROOT . '/' . $page);
        if ($source === false) self::fail('Unable to read ' . $page);
        $code = '';
        foreach (\PhpToken::tokenize($source) as $token) {
            $code .= $token->is(array(T_COMMENT, T_DOC_COMMENT)) ? preg_replace('/[^\n]/', ' ', $token->text) : $token->text;
        }
        return $code;
    }

    // The lines (numbered from 1) where an (int) cast or intval() of a request value isn't the true arm of a ternary
    // whose condition is is_string() and then ctype_digit() for the same field (neither turned around with !), with 0 as
    // the false arm
    private static function uncheckedCasts($source)
    {
        preg_match_all('/(?:\(\s*int(?:eger)?\s*\)|intval\s*\()\s*\(?\s*\$_(POST|GET|REQUEST)\s*\[\s*([\'"])([^\'"]+)\2\s*\]/i',
            $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $unchecked = array();
        foreach ($matches as $match) {
            $offset = $match[0][1];
            $value = '\$_' . $match[1][0] . '\s*\[\s*[\'"]' . preg_quote($match[3][0], '/') . '[\'"]\s*\]';
            $checked = '/(?<!\w)is_string\(\s*' . $value . '\s*(?:\?\?\s*null\s*)?\)\s*&&\s*ctype_digit\(\s*' . $value .
                '\s*\)\s*\?\s*(\(\s*)?(\(int\)\s*' . $value . ')(?(1)\s*\))\s*:\s*0\s*;/';
            $found = false;
            if (preg_match_all($checked, $source, $ternaries, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($ternaries as $ternary) {
                    // the check isn't turned around with !, even with a space after it
                    $before = rtrim(substr($source, 0, $ternary[0][1]));
                    if ($ternary[2][1] === $offset && substr($before, -1) !== '!') $found = true;
                }
            }
            if (!$found) $unchecked[] = substr_count($source, "\n", 0, $offset) + 1;
        }
        return $unchecked;
    }

} //@formatter:on
