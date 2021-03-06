<?php

namespace Oro\Bundle\DataAuditBundle\Tests\Unit\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use Oro\Bundle\DataAuditBundle\EventListener\SegmentWidgetOptionsListener;
use Oro\Bundle\SegmentBundle\Event\WidgetOptionsLoadEvent;
use Oro\Bundle\DataAuditBundle\SegmentWidget\ContextChecker;

class SegmentWidgetOptionsListenerTest extends \PHPUnit_Framework_TestCase
{
    /** @var HttpKernelInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $httpKernel;

    /** @var Request|\PHPUnit_Framework_MockObject_MockObject */
    protected $request;

    /** @var AuthorizationCheckerInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $authorizationChecker;

    /** @var SegmentWidgetOptionsListener */
    protected $listener;

    public function setUp()
    {
        $this->httpKernel = $this->createMock(HttpKernelInterface::class);
        $this->request = $this->createMock(Request::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $this->listener = new SegmentWidgetOptionsListener(
            $this->httpKernel,
            $this->authorizationChecker,
            new ContextChecker()
        );
        $this->listener->setRequest($this->request);
    }

    public function testListener()
    {
        $options = [
            'column'       => [],
            'extensions'   => [],
            'fieldsLoader' => [
                'entityChoice'      => 'choice',
                'loadingMaskParent' => 'loadingMask',
                'confirmMessage'    => 'confirmMessage',
            ],
            'metadata'     => [
                'filters' => [
                    'date' => [
                        'type'      => 'date',
                        'dateParts' => [
                            'value'  => 'val',
                            'source' => 'sour',
                        ],
                    ],
                ],
            ],
        ];

        $auditFields = json_encode(['field1', 'field2']);

        $expectedOptions = [
            'column'            => [],
            'extensions'        => [
                'orodataaudit/js/app/components/segment-component-extension',
            ],
            'fieldsLoader'      => [
                'entityChoice'      => 'choice',
                'loadingMaskParent' => 'loadingMask',
                'confirmMessage'    => 'confirmMessage',
            ],
            'auditFieldsLoader' => [
                'entityChoice'      => 'choice',
                'loadingMaskParent' => 'loadingMask',
                'confirmMessage'    => 'confirmMessage',
                'router'            => 'oro_api_get_audit_fields',
                'routingParams'     => [],
                'fieldsData'        => $auditFields,
            ],
            'metadata'          => [
                'filters' => [
                    'date' => [
                        'type'      => 'date',
                        'dateParts' => [
                            'value'  => 'val',
                            'source' => 'sour',
                        ],
                    ],
                ],
            ],
            'auditFilters'      => [
                'date' => [
                    'type'      => 'date',
                    'dateParts' => [
                        'value' => 'val',
                    ],
                ],
            ],
        ];

        $subRequest = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $this->request->expects($this->once())
            ->method('duplicate')
            ->with(['_format' => 'json'], null, ['_controller' => 'OroDataAuditBundle:Api/Rest/Audit:getFields'])
            ->will($this->returnValue($subRequest));

        $this->httpKernel->expects($this->once())
            ->method('handle')
            ->with($subRequest)
            ->will($this->returnValue(new Response($auditFields)));
        $this->authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->willReturn(true);

        $event = new WidgetOptionsLoadEvent($options);
        $this->listener->onLoad($event);

        $this->assertEquals($expectedOptions, $event->getWidgetOptions());
    }

    public function testOnLoadWhenNotApplicable()
    {
        $options = [
            'column'                       => [],
            'extensions'                   => [],
            'fieldsLoader'                 => [
                'entityChoice'      => 'choice',
                'loadingMaskParent' => 'loadingMask',
                'confirmMessage'    => 'confirmMessage',
            ],
            'metadata'                     => [
                'filters' => [
                    'date' => [
                        'type'      => 'date',
                        'dateParts' => [
                            'value'  => 'val',
                            'source' => 'sour',
                        ],
                    ],
                ],
            ],
            ContextChecker::DISABLED_PARAM => true
        ];

        $event = new WidgetOptionsLoadEvent($options);
        $this->authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->willReturn(true);

        $this->listener->onLoad($event);
        $this->assertEquals($options, $event->getWidgetOptions());
    }

    public function testOnLoadWhenNotGranted()
    {
        $options = [
            'column'       => [],
            'extensions'   => [],
            'fieldsLoader' => [
                'entityChoice'      => 'choice',
                'loadingMaskParent' => 'loadingMask',
                'confirmMessage'    => 'confirmMessage',
            ],
            'metadata'     => [
                'filters' => [
                    'date' => [
                        'type'      => 'date',
                        'dateParts' => [
                            'value'  => 'val',
                            'source' => 'sour',
                        ],
                    ],
                ],
            ]
        ];

        $event = new WidgetOptionsLoadEvent($options);
        $this->authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->willReturn(false);

        $this->listener->onLoad($event);
        $this->assertEquals($options, $event->getWidgetOptions());
    }
}
