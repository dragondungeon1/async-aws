<?php

namespace AsyncAws\CloudFormation;

use AsyncAws\CloudFormation\Input\DescribeStackDriftDetectionStatusInput;
use AsyncAws\CloudFormation\Input\DescribeStackEventsInput;
use AsyncAws\CloudFormation\Input\DescribeStacksInput;
use AsyncAws\CloudFormation\Result\DescribeStackDriftDetectionStatusOutput;
use AsyncAws\CloudFormation\Result\DescribeStackEventsOutput;
use AsyncAws\CloudFormation\Result\DescribeStacksOutput;
use AsyncAws\CloudFormation\ValueObject\Stack;
use AsyncAws\Core\AbstractApi;
use AsyncAws\Core\AwsError\AwsErrorFactoryInterface;
use AsyncAws\Core\AwsError\XmlAwsErrorFactory;
use AsyncAws\Core\Configuration;
use AsyncAws\Core\RequestContext;

class CloudFormationClient extends AbstractApi
{
    /**
     * Returns information about a stack drift detection operation. A stack drift detection operation detects whether a
     * stack's actual configuration differs, or has *drifted*, from it's expected configuration, as defined in the stack
     * template and any values specified as template parameters. A stack is considered to have drifted if one or more of its
     * resources have drifted. For more information about stack and resource drift, see Detecting Unregulated Configuration
     * Changes to Stacks and Resources.
     *
     * @see https://docs.aws.amazon.com/AWSCloudFormation/latest/UserGuide/using-cfn-stack-drift.html
     * @see https://docs.aws.amazon.com/AWSCloudFormation/latest/APIReference/API_DescribeStackDriftDetectionStatus.html
     * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-cloudformation-2010-05-15.html#describestackdriftdetectionstatus
     *
     * @param array{
     *   StackDriftDetectionId: string,
     *   @region?: string,
     * }|DescribeStackDriftDetectionStatusInput $input
     */
    public function describeStackDriftDetectionStatus($input): DescribeStackDriftDetectionStatusOutput
    {
        $input = DescribeStackDriftDetectionStatusInput::create($input);
        $response = $this->getResponse($input->request(), new RequestContext(['operation' => 'DescribeStackDriftDetectionStatus', 'region' => $input->getRegion()]));

        return new DescribeStackDriftDetectionStatusOutput($response);
    }

    /**
     * Returns all stack related events for a specified stack in reverse chronological order. For more information about a
     * stack's event history, go to Stacks in the CloudFormation User Guide.
     *
     * @see https://docs.aws.amazon.com/AWSCloudFormation/latest/UserGuide/concept-stack.html
     * @see https://docs.aws.amazon.com/AWSCloudFormation/latest/APIReference/API_DescribeStackEvents.html
     * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-cloudformation-2010-05-15.html#describestackevents
     *
     * @param array{
     *   StackName?: string,
     *   NextToken?: string,
     *   @region?: string,
     * }|DescribeStackEventsInput $input
     */
    public function describeStackEvents($input = []): DescribeStackEventsOutput
    {
        $input = DescribeStackEventsInput::create($input);
        $response = $this->getResponse($input->request(), new RequestContext(['operation' => 'DescribeStackEvents', 'region' => $input->getRegion()]));

        return new DescribeStackEventsOutput($response, $this, $input);
    }

    /**
     * Returns the description for the specified stack; if no stack name was specified, then it returns the description for
     * all the stacks created.
     *
     * @see https://docs.aws.amazon.com/AWSCloudFormation/latest/APIReference/API_DescribeStacks.html
     * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-cloudformation-2010-05-15.html#describestacks
     *
     * @param array{
     *   StackName?: string,
     *   NextToken?: string,
     *   @region?: string,
     * }|DescribeStacksInput $input
     */
    public function describeStacks($input = []): DescribeStacksOutput
    {
        $input = DescribeStacksInput::create($input);
        $response = $this->getResponse($input->request(), new RequestContext(['operation' => 'DescribeStacks', 'region' => $input->getRegion()]));

        return new DescribeStacksOutput($response, $this, $input);
    }

    protected function getAwsErrorFactory(): AwsErrorFactoryInterface
    {
        return new XmlAwsErrorFactory();
    }

    protected function getEndpointMetadata(?string $region): array
    {
        if (null === $region) {
            $region = Configuration::DEFAULT_REGION;
        }

        switch ($region) {
            case 'cn-north-1':
            case 'cn-northwest-1':
                return [
                    'endpoint' => "https://cloudformation.$region.amazonaws.com.cn",
                    'signRegion' => $region,
                    'signService' => 'cloudformation',
                    'signVersions' => ['v4'],
                ];
            case 'us-iso-east-1':
            case 'us-iso-west-1':
                return [
                    'endpoint' => "https://cloudformation.$region.c2s.ic.gov",
                    'signRegion' => $region,
                    'signService' => 'cloudformation',
                    'signVersions' => ['v4'],
                ];
            case 'us-isob-east-1':
                return [
                    'endpoint' => 'https://cloudformation.us-isob-east-1.sc2s.sgov.gov',
                    'signRegion' => 'us-isob-east-1',
                    'signService' => 'cloudformation',
                    'signVersions' => ['v4'],
                ];
            case 'us-east-1-fips':
                return [
                    'endpoint' => 'https://cloudformation-fips.us-east-1.amazonaws.com',
                    'signRegion' => 'us-east-1',
                    'signService' => 'cloudformation',
                    'signVersions' => ['v4'],
                ];
            case 'us-east-2-fips':
                return [
                    'endpoint' => 'https://cloudformation-fips.us-east-2.amazonaws.com',
                    'signRegion' => 'us-east-2',
                    'signService' => 'cloudformation',
                    'signVersions' => ['v4'],
                ];
            case 'us-west-1-fips':
                return [
                    'endpoint' => 'https://cloudformation-fips.us-west-1.amazonaws.com',
                    'signRegion' => 'us-west-1',
                    'signService' => 'cloudformation',
                    'signVersions' => ['v4'],
                ];
            case 'us-west-2-fips':
                return [
                    'endpoint' => 'https://cloudformation-fips.us-west-2.amazonaws.com',
                    'signRegion' => 'us-west-2',
                    'signService' => 'cloudformation',
                    'signVersions' => ['v4'],
                ];
        }

        return [
            'endpoint' => "https://cloudformation.$region.amazonaws.com",
            'signRegion' => $region,
            'signService' => 'cloudformation',
            'signVersions' => ['v4'],
        ];
    }
}
