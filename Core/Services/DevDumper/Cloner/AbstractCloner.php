<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Core\Services\DevDumper\Cloner;

use Core\Services\DevDumper\Caster\Caster;
use Core\Services\DevDumper\Exception\ThrowingCasterException;

/**
 * AbstractCloner implements a generic caster mechanism for objects and resources.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractCloner implements ClonerInterface
{
    public static $defaultCasters = [
        '__PHP_Incomplete_Class' => ['Core\Services\DevDumper\Caster\Caster', 'castPhpIncompleteClass'],

        'Core\Services\DevDumper\Caster\CutStub' => ['Core\Services\DevDumper\Caster\StubCaster', 'castStub'],
        'Core\Services\DevDumper\Caster\CutArrayStub' => ['Core\Services\DevDumper\Caster\StubCaster', 'castCutArray'],
        'Core\Services\DevDumper\Caster\ConstStub' => ['Core\Services\DevDumper\Caster\StubCaster', 'castStub'],
        'Core\Services\DevDumper\Caster\EnumStub' => ['Core\Services\DevDumper\Caster\StubCaster', 'castEnum'],

        'Closure' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castClosure'],
        'Generator' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castGenerator'],
        'ReflectionType' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castType'],
        'ReflectionAttribute' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castAttribute'],
        'ReflectionGenerator' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castReflectionGenerator'],
        'ReflectionClass' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castClass'],
        'ReflectionClassConstant' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castClassConstant'],
        'ReflectionFunctionAbstract' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castFunctionAbstract'],
        'ReflectionMethod' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castMethod'],
        'ReflectionParameter' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castParameter'],
        'ReflectionProperty' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castProperty'],
        'ReflectionReference' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castReference'],
        'ReflectionExtension' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castExtension'],
        'ReflectionZendExtension' => ['Core\Services\DevDumper\Caster\ReflectionCaster', 'castZendExtension'],

        'Doctrine\Common\Persistence\ObjectManager' => ['Core\Services\DevDumper\Caster\StubCaster', 'cutInternals'],
        'Doctrine\Common\Proxy\Proxy' => ['Core\Services\DevDumper\Caster\DoctrineCaster', 'castCommonProxy'],
        'Doctrine\ORM\Proxy\Proxy' => ['Core\Services\DevDumper\Caster\DoctrineCaster', 'castOrmProxy'],
        'Doctrine\ORM\PersistentCollection' => ['Core\Services\DevDumper\Caster\DoctrineCaster', 'castPersistentCollection'],
        'Doctrine\Persistence\ObjectManager' => ['Core\Services\DevDumper\Caster\StubCaster', 'cutInternals'],

        'DOMException' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castException'],
        'DOMStringList' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castLength'],
        'DOMNameList' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castLength'],
        'DOMImplementation' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castImplementation'],
        'DOMImplementationList' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castLength'],
        'DOMNode' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castNode'],
        'DOMNameSpaceNode' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castNameSpaceNode'],
        'DOMDocument' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castDocument'],
        'DOMNodeList' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castLength'],
        'DOMNamedNodeMap' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castLength'],
        'DOMCharacterData' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castCharacterData'],
        'DOMAttr' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castAttr'],
        'DOMElement' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castElement'],
        'DOMText' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castText'],
        'DOMTypeinfo' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castTypeinfo'],
        'DOMDomError' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castDomError'],
        'DOMLocator' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castLocator'],
        'DOMDocumentType' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castDocumentType'],
        'DOMNotation' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castNotation'],
        'DOMEntity' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castEntity'],
        'DOMProcessingInstruction' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castProcessingInstruction'],
        'DOMXPath' => ['Core\Services\DevDumper\Caster\DOMCaster', 'castXPath'],

        'XMLReader' => ['Core\Services\DevDumper\Caster\XmlReaderCaster', 'castXmlReader'],

        'ErrorException' => ['Core\Services\DevDumper\Caster\ExceptionCaster', 'castErrorException'],
        'Exception' => ['Core\Services\DevDumper\Caster\ExceptionCaster', 'castException'],
        'Error' => ['Core\Services\DevDumper\Caster\ExceptionCaster', 'castError'],
        'Symfony\Bridge\Monolog\Logger' => ['Core\Services\DevDumper\Caster\StubCaster', 'cutInternals'],
        'Symfony\Component\DependencyInjection\ContainerInterface' => ['Core\Services\DevDumper\Caster\StubCaster', 'cutInternals'],
        'Symfony\Component\EventDispatcher\EventDispatcherInterface' => ['Core\Services\DevDumper\Caster\StubCaster', 'cutInternals'],
        'Symfony\Component\HttpClient\CurlHttpClient' => ['Core\Services\DevDumper\Caster\SymfonyCaster', 'castHttpClient'],
        'Symfony\Component\HttpClient\NativeHttpClient' => ['Core\Services\DevDumper\Caster\SymfonyCaster', 'castHttpClient'],
        'Symfony\Component\HttpClient\Response\CurlResponse' => ['Core\Services\DevDumper\Caster\SymfonyCaster', 'castHttpClientResponse'],
        'Symfony\Component\HttpClient\Response\NativeResponse' => ['Core\Services\DevDumper\Caster\SymfonyCaster', 'castHttpClientResponse'],
        'Symfony\Component\HttpFoundation\Request' => ['Core\Services\DevDumper\Caster\SymfonyCaster', 'castRequest'],
        'Core\Services\DevDumper\Exception\ThrowingCasterException' => ['Core\Services\DevDumper\Caster\ExceptionCaster', 'castThrowingCasterException'],
        'Core\Services\DevDumper\Caster\TraceStub' => ['Core\Services\DevDumper\Caster\ExceptionCaster', 'castTraceStub'],
        'Core\Services\DevDumper\Caster\FrameStub' => ['Core\Services\DevDumper\Caster\ExceptionCaster', 'castFrameStub'],
        'Core\Services\DevDumper\Cloner\AbstractCloner' => ['Core\Services\DevDumper\Caster\StubCaster', 'cutInternals'],
        'Symfony\Component\ErrorHandler\Exception\SilencedErrorContext' => ['Core\Services\DevDumper\Caster\ExceptionCaster', 'castSilencedErrorContext'],

        'Imagine\Image\ImageInterface' => ['Core\Services\DevDumper\Caster\ImagineCaster', 'castImage'],

        'Ramsey\Uuid\UuidInterface' => ['Core\Services\DevDumper\Caster\UuidCaster', 'castRamseyUuid'],

        'ProxyManager\Proxy\ProxyInterface' => ['Core\Services\DevDumper\Caster\ProxyManagerCaster', 'castProxy'],
        'PHPUnit_Framework_MockObject_MockObject' => ['Core\Services\DevDumper\Caster\StubCaster', 'cutInternals'],
        'PHPUnit\Framework\MockObject\MockObject' => ['Core\Services\DevDumper\Caster\StubCaster', 'cutInternals'],
        'PHPUnit\Framework\MockObject\Stub' => ['Core\Services\DevDumper\Caster\StubCaster', 'cutInternals'],
        'Prophecy\Prophecy\ProphecySubjectInterface' => ['Core\Services\DevDumper\Caster\StubCaster', 'cutInternals'],
        'Mockery\MockInterface' => ['Core\Services\DevDumper\Caster\StubCaster', 'cutInternals'],

        'PDO' => ['Core\Services\DevDumper\Caster\PdoCaster', 'castPdo'],
        'PDOStatement' => ['Core\Services\DevDumper\Caster\PdoCaster', 'castPdoStatement'],

        'AMQPConnection' => ['Core\Services\DevDumper\Caster\AmqpCaster', 'castConnection'],
        'AMQPChannel' => ['Core\Services\DevDumper\Caster\AmqpCaster', 'castChannel'],
        'AMQPQueue' => ['Core\Services\DevDumper\Caster\AmqpCaster', 'castQueue'],
        'AMQPExchange' => ['Core\Services\DevDumper\Caster\AmqpCaster', 'castExchange'],
        'AMQPEnvelope' => ['Core\Services\DevDumper\Caster\AmqpCaster', 'castEnvelope'],

        'ArrayObject' => ['Core\Services\DevDumper\Caster\SplCaster', 'castArrayObject'],
        'ArrayIterator' => ['Core\Services\DevDumper\Caster\SplCaster', 'castArrayIterator'],
        'SplDoublyLinkedList' => ['Core\Services\DevDumper\Caster\SplCaster', 'castDoublyLinkedList'],
        'SplFileInfo' => ['Core\Services\DevDumper\Caster\SplCaster', 'castFileInfo'],
        'SplFileObject' => ['Core\Services\DevDumper\Caster\SplCaster', 'castFileObject'],
        'SplHeap' => ['Core\Services\DevDumper\Caster\SplCaster', 'castHeap'],
        'SplObjectStorage' => ['Core\Services\DevDumper\Caster\SplCaster', 'castObjectStorage'],
        'SplPriorityQueue' => ['Core\Services\DevDumper\Caster\SplCaster', 'castHeap'],
        'OuterIterator' => ['Core\Services\DevDumper\Caster\SplCaster', 'castOuterIterator'],
        'WeakReference' => ['Core\Services\DevDumper\Caster\SplCaster', 'castWeakReference'],

        'Redis' => ['Core\Services\DevDumper\Caster\RedisCaster', 'castRedis'],
        'RedisArray' => ['Core\Services\DevDumper\Caster\RedisCaster', 'castRedisArray'],
        'RedisCluster' => ['Core\Services\DevDumper\Caster\RedisCaster', 'castRedisCluster'],

        'DateTimeInterface' => ['Core\Services\DevDumper\Caster\DateCaster', 'castDateTime'],
        'DateInterval' => ['Core\Services\DevDumper\Caster\DateCaster', 'castInterval'],
        'DateTimeZone' => ['Core\Services\DevDumper\Caster\DateCaster', 'castTimeZone'],
        'DatePeriod' => ['Core\Services\DevDumper\Caster\DateCaster', 'castPeriod'],

        'GMP' => ['Core\Services\DevDumper\Caster\GmpCaster', 'castGmp'],

        'MessageFormatter' => ['Core\Services\DevDumper\Caster\IntlCaster', 'castMessageFormatter'],
        'NumberFormatter' => ['Core\Services\DevDumper\Caster\IntlCaster', 'castNumberFormatter'],
        'IntlTimeZone' => ['Core\Services\DevDumper\Caster\IntlCaster', 'castIntlTimeZone'],
        'IntlCalendar' => ['Core\Services\DevDumper\Caster\IntlCaster', 'castIntlCalendar'],
        'IntlDateFormatter' => ['Core\Services\DevDumper\Caster\IntlCaster', 'castIntlDateFormatter'],

        'Memcached' => ['Core\Services\DevDumper\Caster\MemcachedCaster', 'castMemcached'],

        'Ds\Collection' => ['Core\Services\DevDumper\Caster\DsCaster', 'castCollection'],
        'Ds\Map' => ['Core\Services\DevDumper\Caster\DsCaster', 'castMap'],
        'Ds\Pair' => ['Core\Services\DevDumper\Caster\DsCaster', 'castPair'],
        'Core\Services\DevDumper\Caster\DsPairStub' => ['Core\Services\DevDumper\Caster\DsCaster', 'castPairStub'],

        'CurlHandle' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castCurl'],
        ':curl' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castCurl'],

        ':dba' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castDba'],
        ':dba persistent' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castDba'],

        'GdImage' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castGd'],
        ':gd' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castGd'],

        ':mysql link' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castMysqlLink'],
        ':pgsql large object' => ['Core\Services\DevDumper\Caster\PgSqlCaster', 'castLargeObject'],
        ':pgsql link' => ['Core\Services\DevDumper\Caster\PgSqlCaster', 'castLink'],
        ':pgsql link persistent' => ['Core\Services\DevDumper\Caster\PgSqlCaster', 'castLink'],
        ':pgsql result' => ['Core\Services\DevDumper\Caster\PgSqlCaster', 'castResult'],
        ':process' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castProcess'],
        ':stream' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castStream'],

        'OpenSSLCertificate' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castOpensslX509'],
        ':OpenSSL X.509' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castOpensslX509'],

        ':persistent stream' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castStream'],
        ':stream-context' => ['Core\Services\DevDumper\Caster\ResourceCaster', 'castStreamContext'],

        'XmlParser' => ['Core\Services\DevDumper\Caster\XmlResourceCaster', 'castXml'],
        ':xml' => ['Core\Services\DevDumper\Caster\XmlResourceCaster', 'castXml'],

        'RdKafka' => ['Core\Services\DevDumper\Caster\RdKafkaCaster', 'castRdKafka'],
        'RdKafka\Conf' => ['Core\Services\DevDumper\Caster\RdKafkaCaster', 'castConf'],
        'RdKafka\KafkaConsumer' => ['Core\Services\DevDumper\Caster\RdKafkaCaster', 'castKafkaConsumer'],
        'RdKafka\Metadata\Broker' => ['Core\Services\DevDumper\Caster\RdKafkaCaster', 'castBrokerMetadata'],
        'RdKafka\Metadata\Collection' => ['Core\Services\DevDumper\Caster\RdKafkaCaster', 'castCollectionMetadata'],
        'RdKafka\Metadata\Partition' => ['Core\Services\DevDumper\Caster\RdKafkaCaster', 'castPartitionMetadata'],
        'RdKafka\Metadata\Topic' => ['Core\Services\DevDumper\Caster\RdKafkaCaster', 'castTopicMetadata'],
        'RdKafka\Message' => ['Core\Services\DevDumper\Caster\RdKafkaCaster', 'castMessage'],
        'RdKafka\Topic' => ['Core\Services\DevDumper\Caster\RdKafkaCaster', 'castTopic'],
        'RdKafka\TopicPartition' => ['Core\Services\DevDumper\Caster\RdKafkaCaster', 'castTopicPartition'],
        'RdKafka\TopicConf' => ['Core\Services\DevDumper\Caster\RdKafkaCaster', 'castTopicConf'],
    ];

    protected $maxItems = 2500;
    protected $maxString = -1;
    protected $minDepth = 1;

    private $casters = [];
    private $prevErrorHandler;
    private $classInfo = [];
    private $filter = 0;

    /**
     * @param callable[]|null $casters A map of casters
     *
     * @see addCasters
     */
    public function __construct(array $casters = null)
    {
        if (null === $casters) {
            $casters = static::$defaultCasters;
        }
        $this->addCasters($casters);
    }

    /**
     * Adds casters for resources and objects.
     *
     * Maps resources or objects types to a callback.
     * Types are in the key, with a callable caster for value.
     * Resource types are to be prefixed with a `:`,
     * see e.g. static::$defaultCasters.
     *
     * @param callable[] $casters A map of casters
     */
    public function addCasters(array $casters)
    {
        foreach ($casters as $type => $callback) {
            $this->casters[$type][] = $callback;
        }
    }

    /**
     * Sets the maximum number of items to clone past the minimum depth in nested structures.
     */
    public function setMaxItems(int $maxItems)
    {
        $this->maxItems = $maxItems;
    }

    /**
     * Sets the maximum cloned length for strings.
     */
    public function setMaxString(int $maxString)
    {
        $this->maxString = $maxString;
    }

    /**
     * Sets the minimum tree depth where we are guaranteed to clone all the items.  After this
     * depth is reached, only setMaxItems items will be cloned.
     */
    public function setMinDepth(int $minDepth)
    {
        $this->minDepth = $minDepth;
    }

    /**
     * Clones a PHP variable.
     *
     * @param mixed $var    Any PHP variable
     * @param int   $filter A bit field of Caster::EXCLUDE_* constants
     *
     * @return Data The cloned variable represented by a Data object
     */
    public function cloneVar($var, int $filter = 0)
    {
        $this->prevErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) {
            if (\E_RECOVERABLE_ERROR === $type || \E_USER_ERROR === $type) {
                // Cloner never dies
                throw new \ErrorException($msg, 0, $type, $file, $line);
            }

            if ($this->prevErrorHandler) {
                return ($this->prevErrorHandler)($type, $msg, $file, $line, $context);
            }

            return false;
        });
        $this->filter = $filter;

        if ($gc = gc_enabled()) {
            gc_disable();
        }
        try {
            return new Data($this->doClone($var));
        } finally {
            if ($gc) {
                gc_enable();
            }
            restore_error_handler();
            $this->prevErrorHandler = null;
        }
    }

    /**
     * Effectively clones the PHP variable.
     *
     * @param mixed $var Any PHP variable
     *
     * @return array The cloned variable represented in an array
     */
    abstract protected function doClone($var);

    /**
     * Casts an object to an array representation.
     *
     * @param bool $isNested True if the object is nested in the dumped structure
     *
     * @return array The object casted as array
     */
    protected function castObject(Stub $stub, bool $isNested)
    {
        $obj = $stub->value;
        $class = $stub->class;

        if (\PHP_VERSION_ID < 80000 ? "\0" === ($class[15] ?? null) : str_contains($class, "@anonymous\0")) {
            $stub->class = get_debug_type($obj);
        }
        if (isset($this->classInfo[$class])) {
            [$i, $parents, $hasDebugInfo, $fileInfo] = $this->classInfo[$class];
        } else {
            $i = 2;
            $parents = [$class];
            $hasDebugInfo = method_exists($class, '__debugInfo');

            foreach (class_parents($class) as $p) {
                $parents[] = $p;
                ++$i;
            }
            foreach (class_implements($class) as $p) {
                $parents[] = $p;
                ++$i;
            }
            $parents[] = '*';

            $r = new \ReflectionClass($class);
            $fileInfo = $r->isInternal() || $r->isSubclassOf(Stub::class) ? [] : [
                'file' => $r->getFileName(),
                'line' => $r->getStartLine(),
            ];

            $this->classInfo[$class] = [$i, $parents, $hasDebugInfo, $fileInfo];
        }

        $stub->attr += $fileInfo;
        $a = Caster::castObject($obj, $class, $hasDebugInfo, $stub->class);

        try {
            while ($i--) {
                if (!empty($this->casters[$p = $parents[$i]])) {
                    foreach ($this->casters[$p] as $callback) {
                        $a = $callback($obj, $a, $stub, $isNested, $this->filter);
                    }
                }
            }
        } catch (\Exception $e) {
            $a = [(Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '').'⚠' => new ThrowingCasterException($e)] + $a;
        }

        return $a;
    }

    /**
     * Casts a resource to an array representation.
     *
     * @param bool $isNested True if the object is nested in the dumped structure
     *
     * @return array The resource casted as array
     */
    protected function castResource(Stub $stub, bool $isNested)
    {
        $a = [];
        $res = $stub->value;
        $type = $stub->class;

        try {
            if (!empty($this->casters[':'.$type])) {
                foreach ($this->casters[':'.$type] as $callback) {
                    $a = $callback($res, $a, $stub, $isNested, $this->filter);
                }
            }
        } catch (\Exception $e) {
            $a = [(Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '').'⚠' => new ThrowingCasterException($e)] + $a;
        }

        return $a;
    }
}
