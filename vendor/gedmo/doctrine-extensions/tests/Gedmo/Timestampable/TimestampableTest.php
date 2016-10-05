<?php

namespace Gedmo\Timestampable;

use Doctrine\Common\EventManager;
use Tool\BaseTestCaseORM;
use Timestampable\Fixture\Article;
use Timestampable\Fixture\Comment;
use Timestampable\Fixture\Type;

/**
 * These are tests for Timestampable behavior
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @link http://www.gediminasm.org
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class TimestampableTest extends BaseTestCaseORM
{
    const ARTICLE = "Timestampable\\Fixture\\Article";
    const COMMENT = "Timestampable\\Fixture\\Comment";
    const TYPE = "Timestampable\\Fixture\\Type";

    protected function setUp()
    {
        parent::setUp();

        $evm = new EventManager();
        $evm->addEventSubscriber(new TimestampableListener());

        $this->getMockSqliteEntityManager($evm);
    }

    /**
     * issue #1255
     * @test
     */
    function shouldHandleDetatchedAndMergedBackEntities()
    {
        $sport = new Article();
        $sport->setTitle('Sport');
        $sport->setBody('Sport article body.');

        $this->em->detach($sport);
        $newSport = $this->em->merge($sport);

        $this->em->persist($newSport);
        $this->em->flush();

        $this->assertNotNull($newSport->getUpdated());
    }

    /**
     * issue #1255
     * @test
     */
    function shouldHandleDetatchedAndMergedBackEntitiesAfterPersist()
    {
        $sport = new Article();
        $sport->setTitle('Sport');
        $sport->setBody('Sport article body.');

        $this->em->persist($sport);
        $this->em->flush();
        $updated = $sport->getUpdated();

        $this->em->detach($sport);
        $newSport = $this->em->merge($sport);

        $this->em->persist($newSport);
        $this->em->flush();

        $this->assertSame($newSport->getUpdated(), $updated, "There was no change, should remain the same");

        $newSport->setTitle('updated');
        $this->em->persist($newSport);
        $this->em->flush();

        $this->assertNotSame($newSport->getUpdated(), $updated, "There was a change, should not remain the same");
    }

    /**
     * @test
     */
    function shouldHandleStandardBehavior()
    {
        $sport = new Article();
        $sport->setTitle('Sport');
        $sport->setBody('Sport article body.');

        $sportComment = new Comment();
        $sportComment->setMessage('hello');
        $sportComment->setArticle($sport);
        $sportComment->setStatus(0);

        $this->em->persist($sport);
        $this->em->persist($sportComment);
        $this->em->flush();

        $sport = $this->em->getRepository(self::ARTICLE)->findOneByTitle('Sport');
        $this->assertNotNull($sc = $sport->getCreated());
        $this->assertNotNull($su = $sport->getUpdated());
        $this->assertNull($sport->getContentChanged());
        $this->assertNull($sport->getPublished());

        $sportComment = $this->em->getRepository(self::COMMENT)->findOneByMessage('hello');
        $this->assertNotNull($scm = $sportComment->getModified());
        $this->assertNull($sportComment->getClosed());

        $sportComment->setStatus(1);
        $published = new Type();
        $published->setTitle('Published');

        $sport->setType($published);
        $this->em->persist($sport);
        $this->em->persist($published);
        $this->em->persist($sportComment);
        $this->em->flush();

        $sportComment = $this->em->getRepository(self::COMMENT)->findOneByMessage('hello');
        $this->assertNotNull($scc = $sportComment->getClosed());
        $this->assertNotNull($sp = $sport->getPublished());

        $sport->setTitle('Updated');
        $this->em->persist($sport);
        $this->em->persist($published);
        $this->em->persist($sportComment);
        $this->em->flush();

        $this->assertSame($sport->getCreated(), $sc, "Date created should remain same after update");
        $this->assertNotSame($su2 = $sport->getUpdated(), $su, "Date updated should change after update");
        $this->assertSame($sport->getPublished(), $sp, "Date published should remain the same after update");
        $this->assertNotSame($scc2 = $sport->getContentChanged(), $scc, "Content must have changed after update");

        $sport->setBody('Body updated');
        $this->em->persist($sport);
        $this->em->persist($published);
        $this->em->persist($sportComment);
        $this->em->flush();

        $this->assertSame($sport->getCreated(), $sc, "Date created should remain same after update");
        $this->assertNotSame($sport->getUpdated(), $su2, "Date updated should change after update");
        $this->assertSame($sport->getPublished(), $sp, "Date published should remain the same after update");
        $this->assertNotSame($sport->getContentChanged(), $scc2, "Content must have changed after update");
    }

    /**
     * @test
     */
    function shouldBeAbleToForceDates()
    {
        $sport = new Article();
        $sport->setTitle('sport forced');
        $sport->setBody('Sport article body.');
        $sport->setCreated(new \DateTime('2000-01-01'));
        $sport->setUpdated(new \DateTime('2000-01-01 12:00:00'));
        $sport->setContentChanged(new \DateTime('2000-01-01 12:00:00'));

        $this->em->persist($sport);
        $this->em->flush();

        $repo = $this->em->getRepository(self::ARTICLE);
        $sport = $repo->findOneByTitle('sport forced');
        $this->assertEquals(
            '2000-01-01',
            $sport->getCreated()->format('Y-m-d')
        );
        $this->assertEquals(
            '2000-01-01 12:00:00',
            $sport->getUpdated()->format('Y-m-d H:i:s')
        );
        $this->assertEquals(
            '2000-01-01 12:00:00',
            $sport->getContentChanged()->format('Y-m-d H:i:s')
        );

        $published = new Type();
        $published->setTitle('Published');

        $sport->setType($published);
        $sport->setPublished(new \DateTime('2000-01-01 12:00:00'));
        $this->em->persist($sport);
        $this->em->persist($published);
        $this->em->flush();

        $sport = $repo->findOneByTitle('sport forced');
        $this->assertEquals(
            '2000-01-01 12:00:00',
            $sport->getPublished()->format('Y-m-d H:i:s')
        );

        $this->em->clear();
    }

    /**
     * @test
     */
    function shouldSolveIssue767()
    {
        $type = new Type();
        $type->setTitle('Published');

        $this->em->persist($type);
        $this->em->flush();
        $this->em->clear();

        $type = $this->em->getReference(self::TYPE, $type->getId());
        $this->assertInstanceOf('Doctrine\ORM\Proxy\Proxy', $type);

        $art = new Article();
        $art->setTitle('Art');
        $art->setBody('body');

        $this->em->persist($art);
        $this->em->flush();

        $art->setType($type);
        $this->em->flush(); // in v2.4.x will work on insert too

        $this->assertNotNull($art->getPublished());
    }

    protected function getUsedEntityFixtures()
    {
        return array(
            self::ARTICLE,
            self::COMMENT,
            self::TYPE,
        );
    }
}
