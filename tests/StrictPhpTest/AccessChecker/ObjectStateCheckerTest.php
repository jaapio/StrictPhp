<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace StrictPhpTest\Aspect;

use phpDocumentor\Reflection\Types\Object_;
use ReflectionProperty;
use StrictPhp\AccessChecker\ObjectStateChecker;
use StrictPhpTestAsset\ClassWithIncorrectlyInitializedParentClassProperties;
use StrictPhpTestAsset\ParentClassWithInitializingConstructor;

/**
 * Tests for {@see \StrictPhp\AccessChecker\ObjectStateChecker}
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 * @license MIT
 *
 * @group Coverage
 *
 * @covers \StrictPhp\AccessChecker\ObjectStateChecker
 */
class ObjectStateCheckerTest extends \PHPUnit_Framework_TestCase
{
    public function testRejectsInvalidObject()
    {
        $checker = new ObjectStateChecker('count', 'count');

        $this->setExpectedException(\InvalidArgumentException::class);

        $checker->__invoke('not an object', __CLASS__);
    }

    public function testAppliesTypeChecksToAllObjectProperties()
    {
        /* @var $applyTypeChecks callable|\PHPUnit_Framework_MockObject_MockObject */
        $applyTypeChecks = $this->getMock('stdClass', ['__invoke']);
        /* @var $findTypes callable|\PHPUnit_Framework_MockObject_MockObject */
        $findTypes       = $this->getMock('stdClass', ['__invoke']);
        $objectType      = new Object_();
        $checker         = new ObjectStateChecker($applyTypeChecks, $findTypes);

        $applyTypeChecks->expects($this->exactly(2))->method('__invoke')->with(
            [$objectType],
            $this->logicalOr(null, ['the child class array'])
        );
        $findTypes
            ->expects($this->exactly(2))
            ->method('__invoke')
            ->with(
                $this->logicalOr(
                    $this->callback(function (ReflectionProperty $property) {
                        return ClassWithIncorrectlyInitializedParentClassProperties::class === $property->getDeclaringClass()->getName();
                    }),
                    $this->callback(function (ReflectionProperty $property) {
                        return ParentClassWithInitializingConstructor::class === $property->getDeclaringClass()->getName();
                    })
                ),
                $this->logicalOr(
                    ClassWithIncorrectlyInitializedParentClassProperties::class,
                    ParentClassWithInitializingConstructor::class
                )
            )
            ->will($this->returnValue([$objectType]));

        $checker->__invoke(
            new ClassWithIncorrectlyInitializedParentClassProperties(),
            ClassWithIncorrectlyInitializedParentClassProperties::class
        );
    }

    public function testAppliesTypeChecksToAllObjectPropertiesOfTheGivenRestrictedScope()
    {
        /* @var $applyTypeChecks callable|\PHPUnit_Framework_MockObject_MockObject */
        $applyTypeChecks = $this->getMock('stdClass', ['__invoke']);
        /* @var $findTypes callable|\PHPUnit_Framework_MockObject_MockObject */
        $findTypes       = $this->getMock('stdClass', ['__invoke']);
        $objectType      = new Object_();
        $checker         = new ObjectStateChecker($applyTypeChecks, $findTypes);

        // note: null because the child class is not calling the parent constructor!
        $applyTypeChecks->expects($this->once())->method('__invoke')->with([$objectType], null);
        $findTypes
            ->expects($this->once())
            ->method('__invoke')
            ->with(
                $this->callback(function (ReflectionProperty $property) {
                    return ParentClassWithInitializingConstructor::class === $property->getDeclaringClass()->getName();
                }),
                ParentClassWithInitializingConstructor::class
            )
            ->will($this->returnValue([$objectType]));

        // we are only checking the properties in the scope of the parent class
        $checker->__invoke(
            new ClassWithIncorrectlyInitializedParentClassProperties(),
            ParentClassWithInitializingConstructor::class
        );
    }
}
