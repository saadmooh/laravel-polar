<<<<<<< HEAD
                                                                                                                                 ### التعديلات
### 1. مقارنة أكواد `Subscription`:
- **الكود المضاف**:
  - **إضافة دالة `paused()`**:
    ```php
    public function paused(): bool
    {
        return $this->ends_at === null ? false : true;
    }
    ```
    - هذه الدالة تُرجع قيمة منطقية (`true` أو `false`) بناءً على ما إذا كان حقل `ends_at` يحتوي على قيمة أم لا. إذا كان `ends_at` يساوي `null`، فإن الاشتراك غير متوقف (`false`)، وإذا كان يحتوي على قيمة (مثل تاريخ انتهاء)، فإن الاشتراك متوقف (`true`).
  - **تعديل دالة `sync()`**:
    ```php
    'product_id' => $attributes['productId'] ?? $attributes['product_id'],
    'price_id' => $attributes['priceId'] ?? $attributes['price_id'],
    ```
    - تم تعديل دالة `sync()` لتدعم كل من `productId` و`product_id`، وكذلك `priceId` و`price_id`، باستخدام عامل الاندماج (`??`) لاختيار القيمة الأولى المتاحة. هذا يعني أن الدالة يمكنها التعامل مع استجابات API التي تستخدم أيًا من هذه المفاتيح، مما يجعلها أكثر مرونة.
  - **استخدام قيم نصية مباشرة بدلاً من تعداد `SubscriptionStatus`**:
    - في الدوال مثل `incomplete()`, `onTrial()`, `active()`, `pastDue()`, `canceled()`, `paused()`, و`ended()`، تم استبدال استخدام تعداد `SubscriptionStatus` (مثل `SubscriptionStatus::Incomplete->value`) بقيم نصية مباشرة مثل `"incomplete"`, `"active"`, `"past_due"`, إلخ. على سبيل المثال:
      ```php
      public function incomplete(): bool
      {
          return $this->status === "incomplete";
      }
      ```
      بدلاً من:
      ```php
      public function incomplete(): bool
      {
          return $this->status === SubscriptionStatus::Incomplete->value;
      }
      ```
    - هذا يعني أن الكود يعتمد الآن على القيم النصية الخام بدلاً من التعداد، مما قد يتطلب ضمان أن جميع القيم المستخدمة متسقة عبر التطبيق.
- **التأثير**:
  - دالة `paused()` تضيف وظيفة جديدة للتحقق من حالة توقف الاشتراك، مما يسهل إدارة الاشتراكات في حالات مثل تعليق الخدمة أو انتهاء الاشتراك مؤقتًا. هذا مفيد في السيناريوهات التي تتطلب التمييز بين الاشتراكات النشطة والمتوقفة.
  - تعديل `sync()` يعزز التوافق مع استجابات API التي قد تستخدم تنسيقات مختلفة للمفاتيح (مثل `productId` في بيئة الإنتاج و`product_id` في بيئة الاختبار). هذا يقلل من احتمالية حدوث أخطاء عند معالجة البيانات من مصادر مختلفة.
  - استخدام القيم النصية بدلاً من التعداد يبسط الكود ويقلل الاعتماد على التعداد، ولكنه قد يؤدي إلى مشاكل إذا لم تكن القيم النصية متسقة مع تلك المستخدمة في أجزاء أخرى من التطبيق أو في استجابات API. قد يتطلب هذا فحصًا إضافيًا لضمان التوافق.

### 2. مقارنة أكواد `LaravelPolar`:
- **الكود المضاف**:
  - **تغيير طريقة HTTP في `updateSubscription`**:
    ```php
    $response = self::api("POST", "v1/subscriptions/$subscriptionId", $request->toArray());
    ```
    - في الكود الأول، كانت الدالة تستخدم طريقة `PATCH` لتحديث الاشتراك، وهي الطريقة القياسية في واجهات REST لتحديث مورد معين. في الكود الثاني، تم تغييرها إلى `POST`، وهي طريقة قد تُستخدم لعمليات غير قياسية أو إذا كان الـ API يتطلب ذلك.
  - **إزالة معالجة المفاتيح في `updateSubscription`**:
    ```php
    // الكود الأول:
    $responseData['userId'] = $response->json()['user_id'] ?? $response->json()['userId'];
    $responseData['productId'] = $response->json()['product_id'] ?? $response->json()['productId'];
    unset($responseData['product_id']);
    unset($responseData['user_id']);
    // الكود الثاني:
    return SubscriptionData::from($response->json());
    ```
    - في الكود الأول، كانت الدالة تعالج استجابة API لتوحيد المفاتيح (`user_id` أو `userId` إلى `userId`، و`product_id` أو `productId` إلى `productId`)، ثم تحذف المفاتيح القديمة. في الكود الثاني، تمت إزالة هذه المعالجة، ويتم إرجاع البيانات مباشرة كما هي من الاستجابة.
  - **إزالة التكرار في `listProducts`**:
    ```php
    // الكود الأول (مع التكرار):
    $response = self::api("GET", "v1/products", $request->toArray());
    try {
        $response = self::api("GET", "v1/products", $request->toArray());
        return ListProductsData::from($response->json());
    } catch (PolarApiError $e) {
        throw new PolarApiError($e->getMessage(), 400);
    }
    // الكود الثاني (بدون تكرار):
    try {
        $response = self::api("GET", "v1/products", $request->toArray());
        return ListProductsData::from($response->json());
    } catch (PolarApiError $e) {
        throw new PolarApiError($e->getMessage(), 400);
    }
    ```
    - في الكود الأول، كان هناك استدعاء مكرر لـ `self::api()` خارج كتلة `try`، وهو غير ضروري لأن الاستجابة يتم الكتابة فوقها داخل الكتلة. في الكود الثاني، تمت إزالة هذا السطر المكرر.
- **التأثير**:
  - تغيير طريقة `PATCH` إلى `POST` في `updateSubscription` قد يكون ضروريًا إذا كان الـ API يدعم `POST` لتحديث الاشتراكات، ولكنه قد يسبب مشاكل إذا كان الـ API مصممًا لاستخدام `PATCH` فقط، مما يتطلب التحقق من وثائق الـ API والاختبار الدقيق.
  - إزالة معالجة المفاتيح في `updateSubscription` تبسط الكود وتقلل من الخطوات المطلوبة، ولكنها قد تؤدي إلى أخطاء إذا كانت استجابات API تستخدم تنسيقات مختلفة للمفاتيح (مثل `user_id` في بيئة الاختبار و`userId` في الإنتاج). هذا يتطلب ضمان أن الـ API يُرجع تنسيقًا موحدًا أو معالجة المفاتيح في مكان آخر.
  - إزالة التكرار في `listProducts` تحسن كفاءة الكود عن طريق تجنب استدعاء API غير ضروري، مما يقلل من زمن التنفيذ ويحسن الأداء، خاصة في الحالات التي تتطلب استدعاءات متكررة.

### 3. مقارنة أكواد `Checkout`:
- **الكود المضاف**:
  - لا توجد إضافات وظيفية (الاختلافات تقتصر على التعليقات).
- **التأثير**:
  - لا يوجد تأثير وظيفي، حيث أن الاختلافات تقتصر على إزالة تعليقات التصحيح (`dd`)، مما يجعل الكود الثاني أنظف وأكثر ملاءمة لبيئة الإنتاج.

### 4. مقارنة أكواد `ProcessWebhook`:
- **الكود المضاف**:
  - **تحديث `email_verified_at` في `handleSubscriptionCreated` و`handleSubscriptionUpdated`**:
    ```php
    $email = $payload['customer']['email'];
    $user = \App\Models\User::where('email', $email)->first();
    if ($user) {
        $user->update(['email_verified_at' => Carbon::now()]);
        $user->save();
    } else {
        Log::warning("User with email {$email} not found for subscription ... event.");
    }
    ```
    - هذا الكود يقوم بالبحث عن مستخدم بناءً على البريد الإلكتروني في الحمولة، وإذا وُجد، يتم تحديث حقل `email_verified_at` بالوقت الحالي، مما يؤكد التحقق من البريد الإلكتروني.
  - **إضافة قيمة افتراضية `"default"` لـ `type` في `handleSubscriptionCreated`**:
    ```php
    'type' => $customerMetadata['subscription_type'] ?? "default",
    ```
    - في الكود الأول، إذا لم يكن مفتاح `subscription_type` موجودًا في `customerMetadata`، يتم استخدام القيمة الافتراضية `"default"` لتعيين حقل `type` للاشتراك. في الكود الثاني، يتم استخدام `customerMetadata['subscription_type']` مباشرة، مما قد يتسبب في خطأ إذا كان المفتاح غير موجود.
  - **إضافة دعم `price_status` في `resolveBillable`**:
    ```php
    if (isset($productMetadata["price_status"])) {
        $email = $payload['customer']['email'];
        $user = \App\Models\User::where('email', $email)->first();
        if ($user) {
            $billableId = $user->id;
            $billableType = "App\Models\User";
            $customerId = (string) $payload['customer_id'];
        } else {
            Log::warning("User with email {$email} not found for subscription updated event.");
        }
    } else {
        $billableId = $customerMetadata['billable_id'] ?? null;
        $billableType = (string) $customerMetadata['billable_type'] ?? null;
        $customerId = (string) $payload['customer_id'] ?? null;
    }
    ```
    - هذا الكود يضيف منطقًا للتعامل مع الحمولات التي تحتوي على مفتاح `price_status` في `productMetadata`. إذا وُجد، يتم البحث عن مستخدم بناءً على البريد الإلكتروني لتحديد `billableId` و`billableType`. إذا لم يكن موجودًا، يتم استخدام القيم من `customerMetadata` مع قيم افتراضية (`null`) إذا لزم الأمر.
  - **إضافة فحص `useremail` في `resolveBillable`**:
    ```php
    $useremail = $payload['user']['email'] ?? null;
    ```
    - يتم استخراج قيمة `useremail` من الحمولة إذا كان مفتاح `user` موجودًا. هذا قد يكون محاولة لدعم حالات إضافية، ولكنه قد لا يُستخدم فعليًا إذا كانت الحمولة لا تحتوي على مفتاح `user`.
- **التأثير**:
  - تحديث `email_verified_at` يضيف وظيفة لتأكيد البريد الإلكتروني عند إنشاء أو تحديث الاشتراكات، مما قد يكون مطلوبًا في سياقات تتطلب التحقق من البريد (مثل الأمان أو الامتثال).
  - إضافة قيمة افتراضية `"default"` لـ `type` تجنب الأخطاء في حالة غياب `subscription_type`، مما يعزز موثوقية الكود عند التعامل مع حمولات ناقصة البيانات.
  - دعم `price_status` يوسع نطاق الحالات التي يمكن لـ `resolveBillable` التعامل معها، مما يسمح بربط الاشتراكات بالمستخدمين حتى لو كانت البيانات تعتمد على `productMetadata` بدلاً من `customerMetadata`. هذا مفيد في السيناريوهات التي تكون فيها الحمولة غير قياسية.
  - فحص `useremail` قد يكون غير فعال إذا لم يكن مفتاح `user` موجودًا في الحمولات النموذجية، مما قد يتطلب مراجعة للتأكد من ضرورته أو إزالته لتبسيط الكود.

### 5. مقارنة أكواد `CheckoutSessionData`:
- **الكود المضاف**:
  - **إضافة فئة `ProductPriceCast` مع تعليمة `#[WithCast(ProductPriceCast::class)]`**:
    ```php
    #[MapName('product_price')]
    #[WithCast(ProductPriceCast::class)]
    public readonly LegacyRecurringProductPriceFixedData|LegacyRecurringProductPriceCustomData|LegacyRecurringProductPriceFreeData|ProductPriceFixedData|ProductPriceCustomData|ProductPriceFreeData|null $productPrice,
    ```
    ```php
    class ProductPriceCast implements Cast
    {
        public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
        {
            if (!is_array($value)) {
                return null;
            }
            $priceType = $value['amount_type'] ?? null;
            $isLegacyRecurring = $value['type'] === "recurring" ? true : false;
            return match (true) {
                $isLegacyRecurring && $priceType === 'fixed' => LegacyRecurringProductPriceFixedData::from($value),
                $isLegacyRecurring && $priceType === 'custom' => LegacyRecurringProductPriceCustomData::from($value),
                $isLegacyRecurring && $priceType === 'free' => LegacyRecurringProductPriceFreeData::from($value),
                !$isLegacyRecurring && $priceType === 'fixed' => ProductPriceFixedData::from($value),
                !$isLegacyRecurring && $priceType === 'custom' => ProductPriceCustomData::from($value),
                !$isLegacyRecurring && $priceType === 'free' => ProductPriceFreeData::from($value),
                default => null,
            };
        }
    }
    ```
    - فئة `ProductPriceCast` تُستخدم لتحويل البيانات الخام لحقل `product_price` إلى الكائن المناسب بناءً على قيمتي `amount_type` (مثل `fixed`, `custom`, `free`) و`type` (إذا كان `recurring` أو غير ذلك). يتم التحقق أولاً مما إذا كانت البيانات مصفوفة، ثم يتم استخراج `amount_type` وتحديد ما إذا كان السعر متكررًا (`type === "recurring"`). بناءً على هذه القيم، يتم اختيار الكلاس المناسب باستخدام تعبير `match`:
      - إذا كان متكررًا (`isLegacyRecurring` صحيح):
        - `fixed` → `LegacyRecurringProductPriceFixedData`
        - `custom` → `LegacyRecurringProductPriceCustomData`
        - `free` → `LegacyRecurringProductPriceFreeData`
      - إذا لم يكن متكررًا:
        - `fixed` → `ProductPriceFixedData`
        - `custom` → `ProductPriceCustomData`
        - `free` → `ProductPriceFreeData`
      - إذا لم يتطابق أي نوع، يُرجع `null`.
- **التأثير**:
  - فئة `ProductPriceCast` تضمن تحويلًا دقيقًا وموثوقًا لحقل `product_price` إلى الكائن المناسب، مما يقلل من احتمالية الأخطاء عند التعامل مع أنواع أسعار مختلفة (ثابتة، مخصصة، مجانية، متكررة أو غير متكررة). هذا مفيد بشكل خاص عندما تكون استجابات API متنوعة أو تحتوي على هياكل بيانات معقدة.
  - بدون `ProductPriceCast` في الكود الثاني، يعتمد التحويل على السلوك الافتراضي لمكتبة `Spatie\LaravelData`، والذي قد يفشل في تحديد النوع الصحيح إذا لم تكن البيانات مطابقة تمامًا لهيكلية الكائنات المتوقعة. هذا قد يؤدي إلى إرجاع `null` أو أخطاء في الحالات الحدودية، مما يقلل من موثوقية الكود.
  - استخدام `ProductPriceCast` يجعل الكود الأول أكثر قوة في التعامل مع تنسيقات البيانات المختلفة، بينما الكود الثاني قد يكون عرضة للأخطاء إذا لم تكن البيانات موحدة.

### ملخص عام:
- **الكود المضاف**: يركز على تعزيز المرونة (مثل دعم `price_status`، قيم افتراضية، ومعالجة مفاتيح متعددة)، إضافة وظائف جديدة (مثل `paused()` وتحديث `email_verified_at`)، وتحسين معالجة البيانات (مثل `ProductPriceCast`). التعديلات تشمل أيضًا تحسينات في الأداء (مثل إزالة التكرار في `listProducts`) وتبسيط الكود (مثل إزالة معالجة المفاتيح في `updateSubscription`).
- **التأثير**: الكود الأول أكثر قوة ومرونة، حيث يتعامل مع تنسيقات API متنوعة وحالات حدودية، ولكنه أكثر تعقيدًا بسبب المنطق الإضافي. الكود الثاني أبسط ولكنه أقل موثوقية في الحالات التي تتطلب معالجة بيانات غير قياسية أو ناقصة، مما قد يؤدي إلى أخطاء إذا لم يتم توحيد البيانات مسبقًا.



Below is a summary of the differences between the provided code snippets (excluding comments and relationships) in English, with the exception of the `email_verified_at` update in `handleSubscriptionCreated` and `handleSubscriptionUpdated`, which will remain in Arabic.

### 1. Comparison of `Subscription` Code:
- **Added Code**:
  - Added `paused()` method:
    ```php
    public function paused(): bool
    {
        return $this->ends_at === null ? false : true;
    }
    ```
  - Modified `sync()` method to support `productId`/`priceId` in addition to `product_id`/`price_id`:
    ```php
    'product_id' => $attributes['productId'] ?? $attributes['product_id'],
    'price_id' => $attributes['priceId'] ?? $attributes['price_id'],
    ```
  - Used direct string values (e.g., `"incomplete"`, `"active"`, `"past_due"`) instead of the `SubscriptionStatus` enum in methods like `incomplete()`, `onTrial()`, `active()`, etc.
- **Impact**:
  - The `paused()` method adds the ability to check if a subscription is paused based on `ends_at`, supporting an additional subscription state.
  - Modifying `sync()` enhances flexibility by handling API responses with varying key formats (`productId`/`priceId` vs. `product_id`/`price_id`), reducing errors due to inconsistent formats.
  - Using string values instead of the `SubscriptionStatus` enum may increase flexibility but risks incompatibility if the enum is used elsewhere, requiring consistent state value handling.

### 2. Comparison of `LaravelPolar` Code:
- **Added Code**:
  - Changed HTTP method in `updateSubscription` from `PATCH` to `POST`:
    ```php
    $response = self::api("POST", "v1/subscriptions/$subscriptionId", $request->toArray());
    ```
  - Removed key normalization for `userId`/`productId` in `updateSubscription`:
    ```php
    // First code:
    $responseData['userId'] = $response->json()['user_id'] ?? $response->json()['userId'];
    $responseData['productId'] = $response->json()['product_id'] ?? $response->json()['productId'];
    unset($responseData['product_id']);
    unset($responseData['user_id']);
    ```
  - Removed redundant API call in `listProducts`:
    ```php
    // First code (with redundancy):
    $response = self::api("GET", "v1/products", $request->toArray());
    try {
        $response = self::api("GET", "v1/products", $request->toArray());
        ...
    }
    // Second code (without redundancy):
    try {
        $response = self::api("GET", "v1/products", $request->toArray());
        ...
    }
    ```
- **Impact**:
  - Switching from `PATCH` to `POST` may align with specific API requirements but could cause incompatibility if the API expects `PATCH` for subscription updates, requiring thorough testing.
  - Removing key normalization simplifies the code but may lead to issues if API responses use varying formats (e.g., `user_id` vs. `userId`), necessitating consistent response formats elsewhere.
  - Removing the redundant API call in `listProducts` improves code quality and performance by avoiding unnecessary API requests.

### 3. Comparison of `Checkout` Code:
- **Added Code**:
  - No functional additions (differences limited to comments).
- **Impact**:
  - No functional impact, as differences are confined to the removal of debugging comments (`dd`), making the second code cleaner and more production-ready.

### 4. Comparison of `ProcessWebhook` Code:
- **Added Code**:
  - تحديث `email_verified_at` في `handleSubscriptionCreated` و`handleSubscriptionUpdated`:
    ```php
    $email = $payload['customer']['email'];
    $user = \App\Models\User::where('email', $email)->first();
    if ($user) {
        $user->update(['email_verified_at' => Carbon::now()]);
        $user->save();
    } else {
        Log::warning("User with email {$email} not found for subscription ... event.");
    }
    ```
  - Added default value `"default"` for `type` in `handleSubscriptionCreated`:
    ```php
    'type' => $customerMetadata['subscription_type'] ?? "default",
    ```
  - Added support for `price_status` in `resolveBillable`:
    ```php
    if (isset($productMetadata["price_status"])) {
        $email = $payload['customer']['email'];
        $user = \App\Models\User::where('email', $email)->first();
        if ($user) {
            $billableId = $user->id;
            $billableType = "App\Models\User";
            $customerId = (string) $payload['customer_id'];
        } else {
            Log::warning("User with email {$email} not found for subscription updated event.");
        }
    } else {
        $billableId = $customerMetadata['billable_id'] ?? null;
        $billableType = (string) $customerMetadata['billable_type'] ?? null;
        $customerId = (string) $payload['customer_id'] ?? null;
    }
    ```
  - Added check for `useremail` in `resolveBillable`:
    ```php
    $useremail = $payload['user']['email'] ?? null;
    ```
- **Impact**:
  - تحديث `email_verified_at` يضيف وظيفة لتأكيد البريد الإلكتروني عند إنشاء أو تحديث الاشتراكات، مما قد يكون مطلوبًا في سياقات تتطلب التحقق من البريد (مثل الأمان أو الامتثال).
  - Adding a default `"default"` for `type` prevents errors if `subscription_type` is missing, enhancing reliability.
  - Supporting `price_status` in `resolveBillable` increases flexibility by handling payloads with `productMetadata` instead of `customerMetadata`, allowing subscription linking via email.
  - Checking `useremail` may be an attempt to support additional cases but could be unused if the `user` key is absent, requiring further review.

### 5. Comparison of `CheckoutSessionData` Code:
- **Added Code**:
  - Added `ProductPriceCast` class with `#[WithCast(ProductPriceCast::class)]` for `product_price`:
    ```php
    #[WithCast(ProductPriceCast::class)]
    public readonly LegacyRecurringProductPriceFixedData|LegacyRecurringProductPriceCustomData|LegacyRecurringProductPriceFreeData|ProductPriceFixedData|ProductPriceCustomData|ProductPriceFreeData|null $productPrice,
    ```
    ```php
    class ProductPriceCast implements Cast
    {
        public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
        {
            if (!is_array($value)) {
                return null;
            }
            $priceType = $value['amount_type'] ?? null;
            $isLegacyRecurring = $value['type'] === "recurring" ? true : false;
            return match (true) {
                $isLegacyRecurring && $priceType === 'fixed' => LegacyRecurringProductPriceFixedData::from($value),
                $isLegacyRecurring && $priceType === 'custom' => LegacyRecurringProductPriceCustomData::from($value),
                $isLegacyRecurring && $priceType === 'free' => LegacyRecurringProductPriceFreeData::from($value),
                !$isLegacyRecurring && $priceType === 'fixed' => ProductPriceFixedData::from($value),
                !$isLegacyRecurring && $priceType === 'custom' => ProductPriceCustomData::from($value),
                !$isLegacyRecurring && $priceType === 'free' => ProductPriceFreeData::from($value),
                default => null,
            };
        }
    }
    ```
- **Impact**:
  - The `ProductPriceCast` class ensures precise conversion of `product_price` to the appropriate class based on `amount_type` (fixed, custom, free) and `type` (recurring or non-recurring), improving reliability.
  - Without `ProductPriceCast` in the second code, conversion relies on `Spatie\LaravelData`'s default behavior, which may lead to errors or `null` if the data doesn't match expected types, reducing reliability in edge cases.

### General Summary:
- **Added Code**: Focuses on enhancing flexibility (e.g., supporting `price_status`, default values, multiple key formats), adding new functionality (e.g., `paused()`, `email_verified_at` update), and improving data handling (e.g., `ProductPriceCast`).
- **Impact**: The first code is more robust and flexible, handling diverse API formats and edge cases, but is more complex. The second code is simpler but less reliable in edge cases, potentially leading to errors if data formats are inconsistent.
=======

# Laravel Polar

<p align="center">
<a href="https://github.com/saadmooh/laravel-polar/actions"><img src="https://github.com/saadmooh/laravel-polar/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/saadmooh/laravel-polar"><img src="https://img.shields.io/packagist/dt/saadmooh/laravel-polar" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/saadmooh/laravel-polar"><img src="https://img.shields.io/packagist/v/saadmooh/laravel-polar" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/saadmooh/laravel-polar"><img src="https://img.shields.io/packagist/l/saadmooh/laravel-polar" alt="License"></a>
</p>

> **Note:** English translation is available below the Arabic section.

## 🇸🇦 العربية

### مقدمة

Laravel Polar هي حزمة تدمج تطبيق Laravel الخاص بك مع [Polar.sh](https://polar.sh) بسلاسة، مما يتيح لك إدارة الاشتراكات والفواتير والمدفوعات بسهولة. توفر هذه الحزمة طريقة بسيطة وأنيقة لتنفيذ الميزات القائمة على الاشتراك في تطبيق Laravel الخاص بك.

### التصحيحات على الحزمة الأصلية

هذه الحزمة هي نسخة مصححة من الحزمة الأصلية، حيث تم إجراء التصحيحات التالية:

1. تغيير طريقة HTTP في دالة `updateSubscription` من PATCH إلى POST لتتوافق مع واجهة برمجة تطبيقات Polar الحالية.
2. تحسين دالة `sync()` في نموذج Subscription لدعم كل من `productId` و `product_id` لضمان التوافق مع مختلف تنسيقات البيانات.
3. تصحيح دوال حالة الاشتراك مثل `active()` و `cancelled()` و `pastDue()` لتعمل بشكل صحيح مع القيم المرجعة من واجهة برمجة التطبيقات.
4. معالجة تنسيق البيانات المستلمة من واجهة برمجة التطبيقات لضمان التوافق مع هيكل البيانات المتوقع في التطبيق.

### المميزات

- تكامل سهل مع واجهة برمجة تطبيقات Polar.sh
- إدارة الاشتراكات
- معالجة الويب هوك للتحديثات في الوقت الفعلي
- مكونات Blade للتكامل مع واجهة المستخدم
- أدوات سطر الأوامر لإدارة المنتجات

### المتطلبات

- PHP ^8.3
- Laravel ^10.0 أو ^11.0 أو ^12.0
- Composer

### التثبيت

1. قم بتثبيت الحزمة عبر Composer:

```bash
composer require saadmooh/laravel-polar
```

2. نشر ملفات الإعدادات:

```bash
php artisan vendor:publish --provider="Saadmooh\LaravelPolar\LaravelPolarServiceProvider"
```

3. تشغيل الترحيلات:

```bash
php artisan migrate
```

4. قم بتكوين بيانات اعتماد واجهة برمجة تطبيقات Polar في ملف `.env`:

```
POLAR_API_KEY=your_api_key
POLAR_WEBHOOK_SECRET=your_webhook_secret
```

### الاستخدام الأساسي

#### إعداد اشتراك

```php
use Saadmooh\LaravelPolar\LaravelPolar;

// إنشاء جلسة دفع
$checkout = LaravelPolar::createCheckout([
    'price_id' => 'price_id_from_polar',
    'success_url' => route('subscription.success'),
    'cancel_url' => route('subscription.cancel'),
]);

// إعادة التوجيه إلى صفحة الدفع
return redirect($checkout->url);
```

#### معالجة الويب هوك

تقوم الحزمة تلقائيًا بإعداد معالجة الويب هوك في المسار المحدد في الإعدادات الخاصة بك. تأكد من أن إعدادات الويب هوك في Polar تشير إلى هذا العنوان.

#### استخدام مكونات Blade

```blade
<x-polar::button :price-id="$priceId" :success-url="route('subscription.success')" :cancel-url="route('subscription.cancel')">
    اشترك الآن
</x-polar::button>
```

#### عرض المنتجات عبر واجهة سطر الأوامر

```bash
php artisan polar:products
```

### الإعدادات

بعد نشر ملفات الإعدادات، يمكنك تخصيص سلوك الحزمة في `config/polar.php` ومعالجة الويب هوك في `config/webhook-client.php`.

### التوثيق

للحصول على توثيق أكثر تفصيلاً، يرجى زيارة [التوثيق الرسمي](https://github.com/saadmooh/laravel-polar/wiki).

### المساهمة

المساهمات مرحب بها! لا تتردد في تقديم طلب سحب.

---

## 🇬🇧 English

### Introduction

Laravel Polar is a package that seamlessly integrates your Laravel application with [Polar.sh](https://polar.sh), allowing you to easily manage subscriptions, billing, and payments. This package provides a simple and elegant way to implement subscription-based features in your Laravel application.

### Corrections to the Original Package

This package is a corrected version of the original package, with the following fixes:

1. Changed the HTTP method in the `updateSubscription` function from PATCH to POST to comply with the current Polar API.
2. Improved the `sync()` function in the Subscription model to support both `productId` and `product_id` to ensure compatibility with different data formats.
3. Fixed subscription status functions like `active()`, `cancelled()`, and `pastDue()` to work correctly with the values returned from the API.
4. Handled the formatting of data received from the API to ensure compatibility with the expected data structure in the application.

### Features

- Easy integration with Polar.sh API
- Subscription management
- Webhook handling for real-time updates
- Blade components for UI integration
- Command-line tools for managing products

### Requirements

- PHP ^8.3
- Laravel ^10.0 or ^11.0 or ^12.0
- Composer

### Installation

1. Install the package via Composer:

```bash
composer require saadmooh/laravel-polar
```

2. Publish the configuration files:

```bash
php artisan vendor:publish --provider="Saadmooh\LaravelPolar\LaravelPolarServiceProvider"
```

3. Run the migrations:

```bash
php artisan migrate
```

4. Configure your Polar API credentials in the `.env` file:

```
POLAR_API_KEY=your_api_key
POLAR_WEBHOOK_SECRET=your_webhook_secret
```

### Basic Usage

#### Setting up a subscription

```php
use Saadmooh\LaravelPolar\LaravelPolar;

// Create a checkout session
$checkout = LaravelPolar::createCheckout([
    'price_id' => 'price_id_from_polar',
    'success_url' => route('subscription.success'),
    'cancel_url' => route('subscription.cancel'),
]);

// Redirect to checkout
return redirect($checkout->url);
```

#### Handling webhooks

The package automatically sets up webhook handling at the route defined in your configuration. Make sure your Polar webhook settings point to this URL.

#### Using Blade components

```blade
<x-polar::button :price-id="$priceId" :success-url="route('subscription.success')" :cancel-url="route('subscription.cancel')">
    Subscribe Now
</x-polar::button>
```

#### Listing products via CLI

```bash
php artisan polar:products
```

### Configuration

After publishing the configuration files, you can customize the package behavior in `config/polar.php` and webhook handling in `config/webhook-client.php`.

### Documentation

For more detailed documentation, please visit the [official documentation](https://github.com/saadmooh/laravel-polar/wiki).

### Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## License / الترخيص

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
>>>>>>> 9deec8e (readme)
