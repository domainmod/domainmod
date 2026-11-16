<?php
/**
 * /tests/Unit/LayoutHelpersTest.php
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

use DomainMOD\Layout;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/*
 * Layout's shared helpers for the design rework: linkButton(), factList(), modal() with its optional message and id,
 * and page actions with a confirmation. Page actions are kept in static properties, so they're emptied before and
 * after each test.
 */
class LayoutHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        $this->clearPageActions();
    }

    protected function tearDown(): void
    {
        $this->clearPageActions();
    }

    public function testLinkButtonIsAnOutlineLinkByDefault()
    {
        $html = (new Layout())->linkButton('../', 'Go Back');

        $this->assertSame('<a class="btn btn-outline-secondary domainmod-css-button" href="../">Go Back</a>', $html);
        $this->assertStringNotContainsString('<button', $html);
    }

    public function testLinkButtonPrimaryIsTheSolidRedButton()
    {
        $html = (new Layout())->linkButton('add/', 'Add Domains', 'primary');

        $this->assertSame('<a class="btn btn-danger domainmod-css-button" href="add/">Add Domains</a>', $html);
    }

    public function testLinkButtonFallsBackToOutlineForAnUnknownVariant()
    {
        $this->assertStringContainsString('class="btn btn-outline-secondary domainmod-css-button"',
            (new Layout())->linkButton('x.php', 'X', 'danger'));
    }

    public function testLinkButtonEscapesItsLabelAndUrl()
    {
        $html = (new Layout())->linkButton('edit.php?a=1&b="2"', '<b>Tom & "Jerry"</b>');

        $this->assertStringContainsString('href="edit.php?a=1&amp;b=&quot;2&quot;"', $html);
        $this->assertStringContainsString('>&lt;b&gt;Tom &amp; &quot;Jerry&quot;&lt;/b&gt;</a>', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function testLinkButtonIconIsDecorativeAndUsesALogicalMargin()
    {
        $html = (new Layout())->linkButton('../', 'Go Back', 'secondary', 'bi-arrow-left');

        // me-1 is a logical margin, so the gap is on the correct side in right-to-left languages too
        $this->assertStringContainsString('><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Go Back</a>', $html);
    }

    public function testLinkButtonSetsTargetRelAndIdEscaped()
    {
        $html = (new Layout())->linkButton('https://example.com/', 'Help', 'secondary', '',
            array('target' => '_blank', 'rel' => 'noopener noreferrer', 'id' => 'a"b'));

        $this->assertSame('<a class="btn btn-outline-secondary domainmod-css-button" href="https://example.com/" target="_blank"'
            . ' rel="noopener noreferrer" id="a&quot;b">Help</a>', $html);
    }

    public function testFactListDrawsEachPairAsATermAndItsValue()
    {
        $html = (new Layout())->factList(array(
            array('label' => 'Registrar', 'value' => 'Namecheap'),
            array('label' => 'Fee', 'value' => '$10.00'),
        ));

        $this->assertSame('<dl class="domainmod-facts"><dt>Registrar</dt><dd>Namecheap</dd><dt>Fee</dt><dd>$10.00</dd></dl>',
            $html);
    }

    public function testFactListEscapesLabelsAndValues()
    {
        $html = (new Layout())->factList(array(array('label' => 'A & <B>', 'value' => '<script>alert("x")</script>')));

        $this->assertStringContainsString('<dt>A &amp; &lt;B&gt;</dt>', $html);
        $this->assertStringContainsString('<dd>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;</dd>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function testFactListIsolatesALeftToRightValue()
    {
        $html = (new Layout())->factList(array(array('label' => 'Domain', 'value' => 'example.com', 'ltr' => true),
            array('label' => 'Name', 'value' => 'Plain')));

        $this->assertStringContainsString('<dd><bdi dir="ltr">example.com</bdi></dd>', $html);
        $this->assertStringContainsString('<dd>Plain</dd>', $html);
    }

    public function testFactListEscapesAnIsolatedValue()
    {
        $html = (new Layout())->factList(array(array('label' => 'Domain', 'value' => '<i>x</i>.com', 'ltr' => true)));

        $this->assertStringContainsString('<bdi dir="ltr">&lt;i&gt;x&lt;/i&gt;.com</bdi>', $html);
    }

    public function testFactListLinkFollowsTheValueWithItsUrlAndLabelEscaped()
    {
        $html = (new Layout())->factList(array(array('label' => 'Inactive Domains', 'value' => '12',
            'url' => 'results.php?type=inactive&segid=3', 'link_label' => 'View <all>')));

        $this->assertSame('<dl class="domainmod-facts"><dt>Inactive Domains</dt><dd>12 <a href="results.php?type=inactive&amp;segid=3">'
            . 'View &lt;all&gt;</a></dd></dl>', $html);
    }

    public function testFactListLinkCanStandWithoutAValue()
    {
        $html = (new Layout())->factList(array(array('label' => 'Report', 'url' => 'r.php', 'link_label' => 'Open')));

        $this->assertStringContainsString('<dd><a href="r.php">Open</a></dd>', $html);
    }

    public function testFactListLeavesOutEmptyPairsAndHalfLinks()
    {
        $html = (new Layout())->factList(array(
            array('label' => 'Empty', 'value' => ''),
            array('label' => 'Missing'),
            array('label' => 'Link without label', 'url' => 'x.php'),
            array('label' => 'Zero', 'value' => '0'),
        ));

        $this->assertSame('<dl class="domainmod-facts"><dt>Zero</dt><dd>0</dd></dl>', $html);
    }

    public function testFactListWithNothingToShowIsEmpty()
    {
        $this->assertSame('', (new Layout())->factList(array()));
        $this->assertSame('', (new Layout())->factList(array(array('label' => 'Empty', 'value' => ''))));
    }

    public function testFactListCanBeStacked()
    {
        $html = (new Layout())->factList(array(array('label' => 'A', 'value' => 'B')), true);

        $this->assertStringStartsWith('<dl class="domainmod-facts domainmod-facts-stacked">', $html);
    }

    #[RunInSeparateProcess]
    public function testFactListMarkupIsTheSameOnRightToLeftPages()
    {
        // The direction comes from the page's dir attribute and the CSS grid, so the markup doesn't change, and a
        // left-to-right value is still isolated
        define('DEFAULT_LANGUAGE', 'ar_SA.UTF-8');
        $layout = new Layout();
        $this->assertTrue($layout->isRtl());

        $this->assertSame('<dl class="domainmod-facts"><dt>Domain</dt><dd><bdi dir="ltr">example.com</bdi></dd></dl>',
            $layout->factList(array(array('label' => 'Domain', 'value' => 'example.com', 'ltr' => true))));
    }

    public function testModalKeepsTheDeleteQuestionAndIdByDefault()
    {
        $html = (new Layout())->modal('Delete Domain', 'example.com', 'delete.php?id=1', 'CANCEL', 'YES, DELETE');

        $this->assertStringContainsString('<div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="myModalTitle" aria-hidden="true">', $html);
        $this->assertStringContainsString('<h4 class="modal-title" id="myModalTitle">Delete Domain</h4>', $html);
        $this->assertStringContainsString('<p>Are you sure you want to delete <strong>example.com</strong>?</p>', $html);
        $this->assertStringContainsString('<a href="delete.php?id=1" class="btn btn-danger">YES, DELETE</a>', $html);
        $this->assertStringContainsString('>CANCEL</button>', $html);
    }

    public function testModalWithAMessageAndIdReplacesTheQuestionAndId()
    {
        $html = (new Layout())->modal('Clear Debug Log', 'unused', 'clear.php', 'CANCEL', 'CLEAR',
            'Clear every entry in the debug log?', 'domainmod-confirm-1');

        $this->assertStringContainsString('id="domainmod-confirm-1" tabindex="-1" aria-labelledby="domainmod-confirm-1Title"', $html);
        $this->assertStringContainsString('<h4 class="modal-title" id="domainmod-confirm-1Title">Clear Debug Log</h4>', $html);
        $this->assertStringContainsString('<p>Clear every entry in the debug log?</p>', $html);
        $this->assertStringNotContainsString('unused', $html);
        $this->assertStringNotContainsString('myModal', $html);
    }

    public function testModalEscapesItsId()
    {
        $html = (new Layout())->modal('T', 'x', 'u', 'L', 'R', '', 'a"b');

        $this->assertStringContainsString('id="a&quot;b"', $html);
        $this->assertStringContainsString('aria-labelledby="a&quot;bTitle"', $html);
    }

    public function testPageActionsWithoutAConfirmationAreUnchangedLinks()
    {
        $layout = new Layout();
        $layout->addPageAction('Export', 'index.php?export_data=1', 'bi-download');

        $this->assertSame('<div class="domainmod-page-actions d-flex flex-wrap align-items-center gap-2" role="group" aria-label="Page actions">'
            . '<a class="btn btn-outline-secondary btn-sm" href="index.php?export_data=1"><i class="bi bi-download me-1" aria-hidden="true"></i>Export</a>'
            . '</div>', $layout->renderPageActions());
    }

    public function testAConfirmedPageActionIsAButtonThatOpensItsConfirmation()
    {
        $layout = new Layout();
        $layout->addPageAction('Export', 'index.php?export_data=1', 'bi-download');
        $layout->addPageAction('Clear Debug Log', '../maintenance/clear-log.php?a=1&token=abc', 'bi-trash',
            array('confirm' => 'Clear every entry in the debug log?', 'id' => 'clear-log'));
        $html = $layout->renderPageActions();

        // The button is in the actions' group, and the confirmation follows the group
        $this->assertStringContainsString('<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"'
            . ' data-bs-target="#domainmod-confirm-2" id="clear-log"><i class="bi bi-trash me-1" aria-hidden="true"></i>Clear Debug Log</button></div>', $html);
        $this->assertStringContainsString('<div class="modal fade" id="domainmod-confirm-2"', $html);
        $this->assertStringContainsString('<h4 class="modal-title" id="domainmod-confirm-2Title">Clear Debug Log</h4>', $html);
        $this->assertStringContainsString('<p>Clear every entry in the debug log?</p>', $html);
        // Only the Yes button follows the URL, escaped once
        $this->assertStringContainsString('<a href="../maintenance/clear-log.php?a=1&amp;token=abc" class="btn btn-danger">CLEAR DEBUG LOG</a>', $html);
        $this->assertStringNotContainsString('<a class="btn btn-outline-secondary btn-sm" href="../maintenance/clear-log.php', $html);
        $this->assertStringNotContainsString('myModal', $html);
    }

    public function testAConfirmedPageActionIgnoresTargetAndRel()
    {
        $layout = new Layout();
        $layout->addPageAction('Clear', 'clear.php', 'bi-trash', array('confirm' => 'Clear it?', 'target' => '_blank',
            'rel' => 'noopener'));
        $html = $layout->renderPageActions();

        $this->assertStringNotContainsString(' target=', $html);
        $this->assertStringNotContainsString(' rel=', $html);
        $this->assertStringContainsString('<a href="clear.php" class="btn btn-danger">CLEAR</a>', $html);
    }

    public function testAConfirmationCanSetItsTitleAndButtonAndEscapesThem()
    {
        $layout = new Layout();
        $layout->addPageAction('Rebuild', 'rebuild.php', 'bi-arrow-repeat', array('confirm' => array(
            'message' => 'Rebuild <now> & "later"?', 'title' => 'Rebuild <DW>', 'button' => 'Yes, rebuild')));
        $html = $layout->renderPageActions();

        $this->assertStringContainsString('<h4 class="modal-title" id="domainmod-confirm-1Title">Rebuild &lt;DW&gt;</h4>', $html);
        $this->assertStringContainsString('<p>Rebuild &lt;now&gt; &amp; &quot;later&quot;?</p>', $html);
        $this->assertStringContainsString('class="btn btn-danger">YES, REBUILD</a>', $html);
    }

    public function testAConfirmationWithoutAQuestionNeverAsksAboutADelete()
    {
        $layout = new Layout();
        $layout->addPageAction('Rebuild', 'rebuild.php', 'bi-arrow-repeat', array('confirm' => array('title' => 'Rebuild DW')));
        $html = $layout->renderPageActions();

        $this->assertStringContainsString('<p>Rebuild DW</p>', $html);
        $this->assertStringNotContainsString('delete', $html);
    }

    private function clearPageActions()
    {
        $reflection = new \ReflectionClass(Layout::class);
        $reflection->setStaticPropertyValue('page_actions', array());
        $reflection->setStaticPropertyValue('page_add_action', null);
        $reflection->setStaticPropertyValue('page_add_menu', array());
    }

} //@formatter:on
