<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Constraints;

use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Constraints\Min;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Collection\Required;
use Symfony\Component\Validator\Constraints\Collection\Optional;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\CollectionValidator;

abstract class CollectionValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;
    protected $walker;
    protected $context;

    protected function setUp()
    {
        $this->walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $metadataFactory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');

        $this->context = new ExecutionContext('Root', $this->walker, $metadataFactory);

        $this->validator = new CollectionValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->validator = null;
        $this->walker = null;
        $this->context = null;
    }

    abstract protected function prepareTestData(array $contents);

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Collection(array('fields' => array(
            'foo' => new Min(4),
        )))));
    }

    public function testFieldsAsDefaultOption()
    {
        $data = $this->prepareTestData(array('foo' => 'foobar'));

        $this->assertTrue($this->validator->isValid($data, new Collection(array(
            'foo' => new Min(4),
        ))));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testThrowsExceptionIfNotTraversable()
    {
        $this->validator->isValid('foobar', new Collection(array('fields' => array(
            'foo' => new Min(4),
        ))));
    }

    public function testWalkSingleConstraint()
    {
        $this->context->setGroup('MyGroup');
        $this->context->setPropertyPath('foo');

        $constraint = new Min(4);

        $array = array('foo' => 3);

        foreach ($array as $key => $value) {
            $this->walker->expects($this->once())
                ->method('walkConstraint')
                ->with($this->equalTo($constraint), $this->equalTo($value), $this->equalTo('MyGroup'), $this->equalTo('foo['.$key.']'));
        }

        $data = $this->prepareTestData($array);

        $this->assertTrue($this->validator->isValid($data, new Collection(array(
            'fields' => array(
                'foo' => $constraint,
            ),
        ))));
    }

    public function testWalkMultipleConstraints()
    {
        $this->context->setGroup('MyGroup');
        $this->context->setPropertyPath('foo');

        $constraints = array(
            new Min(4),
            new NotNull(),
        );
        $array = array('foo' => 3);

        foreach ($array as $key => $value) {
            foreach ($constraints as $i => $constraint) {
                $this->walker->expects($this->at($i))
                    ->method('walkConstraint')
                    ->with($this->equalTo($constraint), $this->equalTo($value), $this->equalTo('MyGroup'), $this->equalTo('foo['.$key.']'));
            }
        }

        $data = $this->prepareTestData($array);

        $this->assertTrue($this->validator->isValid($data, new Collection(array(
            'fields' => array(
                'foo' => $constraints,
            )
        ))));
    }

    public function testExtraFieldsDisallowed()
    {
        $data = $this->prepareTestData(array(
            'foo' => 5,
            'bar' => 6,
        ));

        $this->assertFalse($this->validator->isValid($data, new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
        ))));
    }

    // bug fix
    public function testNullNotConsideredExtraField()
    {
        $data = $this->prepareTestData(array(
            'foo' => null,
        ));
        $collection = new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
        ));

        $this->assertTrue($this->validator->isValid($data, $collection));
    }

    public function testExtraFieldsAllowed()
    {
        $data = $this->prepareTestData(array(
            'foo' => 5,
            'bar' => 6,
        ));
        $collection = new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
            'allowExtraFields' => true,
        ));

        $this->assertTrue($this->validator->isValid($data, $collection));
    }

    public function testMissingFieldsDisallowed()
    {
        $data = $this->prepareTestData(array());

        $this->assertFalse($this->validator->isValid($data, new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
        ))));
    }

    public function testMissingFieldsAllowed()
    {
        $data = $this->prepareTestData(array());

        $this->assertTrue($this->validator->isValid($data, new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
            'allowMissingFields' => true,
        ))));
    }

    public function testOptionalFieldPresent()
    {
        $data = $this->prepareTestData(array(
            'foo' => null,
        ));

        $this->assertTrue($this->validator->isValid($data, new Collection(array(
            'foo' => new Optional(),
        ))));
    }

    public function testOptionalFieldNotPresent()
    {
        $data = $this->prepareTestData(array());

        $this->assertTrue($this->validator->isValid($data, new Collection(array(
            'foo' => new Optional(),
        ))));
    }

    public function testOptionalFieldSingleConstraint()
    {
        $this->context->setGroup('MyGroup');
        $this->context->setPropertyPath('bar');

        $array = array(
            'foo' => 5,
        );

        $constraint = new Min(4);

        $this->walker->expects($this->once())
            ->method('walkConstraint')
            ->with($this->equalTo($constraint), $this->equalTo($array['foo']), $this->equalTo('MyGroup'), $this->equalTo('bar[foo]'));

        $data = $this->prepareTestData($array);

        $this->assertTrue($this->validator->isValid($data, new Collection(array(
            'foo' => new Optional($constraint),
        ))));
    }

    public function testOptionalFieldMultipleConstraints()
    {
        $this->context->setGroup('MyGroup');
        $this->context->setPropertyPath('bar');

        $array = array(
            'foo' => 5,
        );

        $constraints = array(
            new NotNull(),
            new Min(4),
        );

        foreach ($constraints as $i => $constraint) {
            $this->walker->expects($this->at($i))
                ->method('walkConstraint')
                ->with($this->equalTo($constraint), $this->equalTo($array['foo']), $this->equalTo('MyGroup'), $this->equalTo('bar[foo]'));
        }

        $data = $this->prepareTestData($array);

        $this->assertTrue($this->validator->isValid($data, new Collection(array(
            'foo' => new Optional($constraints),
        ))));
    }

    public function testRequiredFieldPresent()
    {
        $data = $this->prepareTestData(array(
            'foo' => null,
        ));

        $this->assertTrue($this->validator->isValid($data, new Collection(array(
            'foo' => new Required(),
        ))));
    }

    public function testRequiredFieldNotPresent()
    {
        $data = $this->prepareTestData(array());

        $this->assertFalse($this->validator->isValid($data, new Collection(array(
            'foo' => new Required(),
        ))));
    }

    public function testRequiredFieldSingleConstraint()
    {
        $this->context->setGroup('MyGroup');
        $this->context->setPropertyPath('bar');

        $array = array(
            'foo' => 5,
        );

        $constraint = new Min(4);

        $this->walker->expects($this->once())
            ->method('walkConstraint')
            ->with($this->equalTo($constraint), $this->equalTo($array['foo']), $this->equalTo('MyGroup'), $this->equalTo('bar[foo]'));

        $data = $this->prepareTestData($array);

        $this->assertTrue($this->validator->isValid($data, new Collection(array(
            'foo' => new Required($constraint),
        ))));
    }

    public function testRequiredFieldMultipleConstraints()
    {
        $this->context->setGroup('MyGroup');
        $this->context->setPropertyPath('bar');

        $array = array(
            'foo' => 5,
        );

        $constraints = array(
            new NotNull(),
            new Min(4),
        );

        foreach ($constraints as $i => $constraint) {
            $this->walker->expects($this->at($i))
                ->method('walkConstraint')
                ->with($this->equalTo($constraint), $this->equalTo($array['foo']), $this->equalTo('MyGroup'), $this->equalTo('bar[foo]'));
        }

        $data = $this->prepareTestData($array);

        $this->assertTrue($this->validator->isValid($array, new Collection(array(
            'foo' => new Required($constraints),
        ))));
    }

    public function testObjectShouldBeLeftUnchanged()
    {
        $value = new \ArrayObject(array(
            'foo' => 3
        ));
        $this->validator->isValid($value, new Collection(array(
            'fields' => array(
                'foo' => new Min(2),
            )
        )));

        $this->assertEquals(array(
            'foo' => 3
        ), (array) $value);
    }
}
