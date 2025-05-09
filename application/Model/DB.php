<?php
namespace Application\Model;

/**
 * کلاس ارتباط با پایگاه داده
 */
class DB
{
    /**
     * اتصال به پایگاه داده
     * @var \PDO|null
     */
    private static $db = null;
    
    /**
     * دریافت اتصال به پایگاه داده
     * @return \PDO
     */
    public static function getConnection()
    {
        if (self::$db === null) {
            try {
                if (isset($_ENV['DATABASE_URL'])) {
                    // پارس کردن DATABASE_URL
                    $url = parse_url($_ENV['DATABASE_URL']);
                    $host = $url['host'];
                    $port = $url['port'] ?? '5432';
                    $dbname = ltrim($url['path'], '/');
                    $user = $url['user'];
                    $password = $url['pass'];
                    
                    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
                    self::$db = new \PDO($dsn, $user, $password);
                } else {
                    // استفاده از متغیرهای محیطی PostgreSQL
                    $host = $_ENV['PGHOST'] ?? 'localhost';
                    $port = $_ENV['PGPORT'] ?? '5432';
                    $dbname = $_ENV['PGDATABASE'] ?? 'postgres';
                    $user = $_ENV['PGUSER'] ?? 'postgres';
                    $password = $_ENV['PGPASSWORD'] ?? 'postgres';
                    
                    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
                    self::$db = new \PDO($dsn, $user, $password);
                }
                self::$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                self::$db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
                self::$db->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false); // بهبود امنیت و کارایی
                self::$db->setAttribute(\PDO::ATTR_PERSISTENT, true); // استفاده از اتصال ماندگار
            } catch (\PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                die("Database connection error: " . $e->getMessage());
            }
        }
        
        return self::$db;
    }
    
    /**
     * اجرای کوئری
     * @param string $query کوئری
     * @param array $params پارامترها
     * @return \PDOStatement
     */
    public static function query($query, $params = [])
    {
        try {
            $stmt = self::getConnection()->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            throw new \Exception("Database query error: " . $e->getMessage());
        }
    }
    
    /**
     * اجرای کوئری خام SQL
     * @param string $query کوئری
     * @param array $params پارامترهای کوئری (اختیاری)
     * @return array نتیجه به صورت آرایه
     */
    public static function rawQuery($query, $params = [])
    {
        try {
            if (empty($params)) {
                // اگر پارامتری نداریم از query استفاده می‌کنیم
                $stmt = self::getConnection()->query($query);
            } else {
                // اگر پارامتر داریم از prepare و execute استفاده می‌کنیم
                $stmt = self::getConnection()->prepare($query);
                $stmt->execute($params);
            }
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Database raw query error: " . $e->getMessage());
            throw new \Exception("Database raw query error: " . $e->getMessage());
        }
    }
    
    /**
     * اجرای کوئری SQL خام و بازگرداندن مقدار
     * @param string $query کوئری
     * @param array $params پارامترهای کوئری (اختیاری)
     * @return mixed نتیجه اجرای کوئری
     */
    public static function raw($query, $params = [])
    {
        try {
            if (empty($params)) {
                $stmt = self::getConnection()->query($query);
            } else {
                $stmt = self::getConnection()->prepare($query);
                $stmt->execute($params);
            }
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Database raw error: " . $e->getMessage());
            throw new \Exception("Database raw error: " . $e->getMessage());
        }
    }
    
    /**
     * ساخت کوئری بیلدر برای جدول مشخص
     * @param string $table نام جدول
     * @return QueryBuilder
     */
    public static function table($table)
    {
        return new QueryBuilder($table);
    }
}

/**
 * کلاس بیلدر کوئری
 */
class QueryBuilder
{
    /**
     * نام جدول
     * @var string
     */
    private $table;
    
    /**
     * شرط‌های کوئری
     * @var array
     */
    private $wheres = [];
    
    /**
     * پارامترها
     * @var array
     */
    private $params = [];
    
    /**
     * ستون‌های انتخابی
     * @var string
     */
    private $selects = '*';
    
    /**
     * محدودیت کوئری
     * @var int|null
     */
    private $limit = null;
    
    /**
     * آفست کوئری
     * @var int|null
     */
    private $offset = null;
    
    /**
     * مرتب‌سازی کوئری
     * @var string|null
     */
    private $orderBy = null;
    
    /**
     * متد جوین
     * @var array
     */
    private $joins = [];
    
    /**
     * سازنده
     * @param string $table نام جدول
     */
    public function __construct($table)
    {
        $this->table = $table;
    }
    
    /**
     * افزودن شرط
     * @param string $column نام ستون
     * @param string $operator عملگر
     * @param mixed $value مقدار
     * @return $this
     */
    public function where($column, $operator = '=', $value = null)
    {
        // پشتیبانی از فرمت where($column, $value)
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->wheres[] = "$column $operator ?";
        $this->params[] = $value;
        
        return $this;
    }
    
    /**
     * افزودن شرط OR
     * @param string $column نام ستون
     * @param string $operator عملگر
     * @param mixed $value مقدار
     * @return $this
     */
    public function orWhere($column, $operator = '=', $value = null)
    {
        // پشتیبانی از فرمت orWhere($column, $value)
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        if (empty($this->wheres)) {
            return $this->where($column, $operator, $value);
        }
        
        $this->wheres[] = "OR $column $operator ?";
        $this->params[] = $value;
        
        return $this;
    }
    
    /**
     * افزودن شرط IN
     * @param string $column نام ستون
     * @param array $values مقادیر
     * @return $this
     */
    public function whereIn($column, array $values)
    {
        if (empty($values)) {
            return $this;
        }
        
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = "$column IN ($placeholders)";
        $this->params = array_merge($this->params, $values);
        
        return $this;
    }
    
    /**
     * افزودن شرط خام (Raw)
     * @param string $condition شرط خام
     * @param array $params پارامترها (اختیاری)
     * @return $this
     */
    public function whereRaw($condition, $params = [])
    {
        $this->wheres[] = $condition;
        $this->params = array_merge($this->params, is_array($params) ? $params : [$params]);
        
        return $this;
    }
    
    /**
     * انتخاب ستون‌ها
     * @param string|array $columns ستون‌ها
     * @return $this
     */
    public function select($columns = '*')
    {
        if (is_array($columns)) {
            $this->selects = implode(', ', $columns);
        } else {
            $this->selects = $columns;
        }
        
        return $this;
    }
    
    /**
     * تنظیم محدودیت
     * @param int $limit محدودیت
     * @return $this
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * تنظیم آفست
     * @param int $offset آفست
     * @return $this
     */
    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }
    
    /**
     * تنظیم مرتب‌سازی
     * @param string $column ستون
     * @param string $direction جهت مرتب‌سازی
     * @return $this
     */
    public function orderBy($column, $direction = 'ASC')
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }
        
        $this->orderBy = "$column $direction";
        return $this;
    }
    
    /**
     * افزودن جوین
     * @param string $table جدول
     * @param string $first ستون اول
     * @param string $operator عملگر
     * @param string $second ستون دوم
     * @param string $type نوع جوین
     * @return $this
     */
    public function join($table, $first, $operator, $second, $type = 'INNER')
    {
        $this->joins[] = "$type JOIN $table ON $first $operator $second";
        return $this;
    }
    
    /**
     * افزودن لفت جوین
     * @param string $table جدول
     * @param string $first ستون اول
     * @param string $operator عملگر
     * @param string $second ستون دوم
     * @return $this
     */
    public function leftJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }
    
    /**
     * افزودن رایت جوین
     * @param string $table جدول
     * @param string $first ستون اول
     * @param string $operator عملگر
     * @param string $second ستون دوم
     * @return $this
     */
    public function rightJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }
    
    /**
     * دریافت همه نتایج
     * @return array
     */
    public function get()
    {
        $query = $this->buildSelectQuery();
        $stmt = DB::query($query, $this->params);
        return $stmt->fetchAll();
    }
    
    /**
     * دریافت اولین نتیجه
     * @return array|null
     */
    public function first()
    {
        $this->limit(1);
        $query = $this->buildSelectQuery();
        $stmt = DB::query($query, $this->params);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * شمارش نتایج
     * @param string $column ستون
     * @return int
     */
    public function count($column = '*')
    {
        $this->selects = "COUNT($column) as count";
        $query = $this->buildSelectQuery();
        $stmt = DB::query($query, $this->params);
        $result = $stmt->fetch();
        return (int) ($result['count'] ?? 0);
    }
    
    /**
     * دریافت مقدار یک ستون
     * @param string $column نام ستون
     * @return mixed مقدار ستون یا null در صورت عدم وجود
     */
    public function value($column)
    {
        $this->selects = $column;
        $this->limit(1);
        $query = $this->buildSelectQuery();
        $stmt = DB::query($query, $this->params);
        $result = $stmt->fetch();
        return $result ? $result[$column] : null;
    }
    
    /**
     * محاسبه میانگین
     * @param string $column نام ستون
     * @return float|null
     */
    public function avg($column)
    {
        $this->selects = "AVG($column) as avg_value";
        $query = $this->buildSelectQuery();
        $stmt = DB::query($query, $this->params);
        $result = $stmt->fetch();
        return $result ? (float)$result['avg_value'] : null;
    }
    
    /**
     * درج داده جدید
     * @param array $data داده‌ها
     * @return int آیدی سطر جدید
     */
    public function insert(array $data)
    {
        $columns = implode(', ', array_keys($data));
        $values = implode(', ', array_fill(0, count($data), '?'));
        
        $query = "INSERT INTO {$this->table} ($columns) VALUES ($values)";
        DB::query($query, array_values($data));
        
        return DB::getConnection()->lastInsertId();
    }
    
    /**
     * درج داده‌های جدید
     * @param array $data داده‌ها
     * @return array آیدی‌های سطرهای جدید
     */
    public function insertMultiple(array $data)
    {
        if (empty($data)) {
            return [];
        }
        
        $columns = implode(', ', array_keys($data[0]));
        $allValues = [];
        $params = [];
        
        foreach ($data as $row) {
            $rowPlaceholders = implode(', ', array_fill(0, count($row), '?'));
            $allValues[] = "($rowPlaceholders)";
            $params = array_merge($params, array_values($row));
        }
        
        $values = implode(', ', $allValues);
        $query = "INSERT INTO {$this->table} ($columns) VALUES $values RETURNING id";
        $stmt = DB::query($query, $params);
        
        $ids = [];
        while ($row = $stmt->fetch()) {
            $ids[] = $row['id'];
        }
        
        return $ids;
    }
    
    /**
     * به‌روزرسانی داده‌ها
     * @param array $data داده‌ها
     * @return int تعداد سطرهای تغییر یافته
     */
    public function update(array $data)
    {
        $sets = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $sets[] = "$column = ?";
            $params[] = $value;
        }
        
        $params = array_merge($params, $this->params);
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $sets);
        
        if (!empty($this->wheres)) {
            $query .= " WHERE " . implode(' AND ', $this->wheres);
        }
        
        $stmt = DB::query($query, $params);
        return $stmt->rowCount();
    }
    
    /**
     * حذف داده‌ها
     * @return int تعداد سطرهای حذف شده
     */
    public function delete()
    {
        $query = "DELETE FROM {$this->table}";
        
        if (!empty($this->wheres)) {
            $query .= " WHERE " . implode(' AND ', $this->wheres);
        }
        
        $stmt = DB::query($query, $this->params);
        return $stmt->rowCount();
    }
    
    /**
     * ساخت کوئری انتخاب
     * @return string
     */
    private function buildSelectQuery()
    {
        $query = "SELECT {$this->selects} FROM {$this->table}";
        
        if (!empty($this->joins)) {
            $query .= ' ' . implode(' ', $this->joins);
        }
        
        if (!empty($this->wheres)) {
            $query .= " WHERE " . implode(' AND ', $this->wheres);
        }
        
        if ($this->orderBy !== null) {
            $query .= " ORDER BY {$this->orderBy}";
        }
        
        if ($this->limit !== null) {
            $query .= " LIMIT {$this->limit}";
        }
        
        if ($this->offset !== null) {
            $query .= " OFFSET {$this->offset}";
        }
        
        return $query;
    }
    
    /**
     * پاکسازی کوئری
     * @return $this
     */
    public function reset()
    {
        $this->wheres = [];
        $this->params = [];
        $this->selects = '*';
        $this->limit = null;
        $this->offset = null;
        $this->orderBy = null;
        $this->joins = [];
        
        return $this;
    }
}