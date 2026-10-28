<x-app-layout title="Как это работает">
    <x-slot name="header">Как это работает</x-slot>

    <div class="max-w-3xl space-y-6 text-gray-700 leading-relaxed">
        <x-card title="Идея в трёх шагах">
            <ol class="list-decimal pl-5 space-y-2">
                <li><span class="font-medium">Вступите в группу и внесите ставку.</span> Пополните баланс и присоединитесь к челленджу — ставка блокируется на вашем балансе.</li>
                <li><span class="font-medium">Каждое утро подтверждайте подъём на Фаджр.</span> В случайный момент после наступления Фаджра на панели появляется код — введите его.</li>
                <li><span class="font-medium">Дойдите до конца.</span> Выдержавшие получают свою ставку назад и делят ставки тех, кто пропустил день.</li>
            </ol>
        </x-card>

        <x-card title="Услуги и стоимость">
            <ul class="list-disc pl-5 space-y-2">
                <li><span class="font-medium">Услуга:</span> доступ к платформе и участие в групповых челленджах на ранний подъём к молитве Фаджр — выдача ежедневного кода, учёт подтверждений, подведение итогов и выплаты.</li>
                <li><span class="font-medium">Стоимость:</span> фиксированная комиссия {{ $percent }}% с каждого пополнения баланса. Регистрация и пользование сайтом бесплатны, абонентской платы нет.</li>
                <li><span class="font-medium">Ставка</span> — не плата за услугу, а ваш залог: от <x-money :amount="$stakeMin" /> до <x-money :amount="$stakeMax" />. Выдержавшие челлендж получают её назад.</li>
                <li><span class="font-medium">Как вы получаете услугу:</span> услуга цифровая, доставки нет. Сразу после пополнения баланса вы можете вступать в челленджи, а ежедневный код появляется прямо на сайте в разделе «Подтверждение».</li>
            </ul>
        </x-card>

        <x-card title="Ежедневное подтверждение">
            <ul class="list-disc pl-5 space-y-2">
                <li>Время Фаджра рассчитывается для вашего города. Сайт показывает, когда начнётся и закончится окно, в которое появится код.</li>
                <li>Код появляется в <span class="font-medium">случайный момент</span> в течение {{ $revealLength }} минут после Фаджра. Заранее его не узнать — нужно бодрствовать и следить за страницей.</li>
                <li>Код действует {{ $codeValid }} минут и всегда истекает до восхода солнца. Страница обновляется сама.</li>
                <li>Неверный код можно ввести не больше {{ $maxAttempts }} раз за день.</li>
                <li>Пропустили день — вы исключены из челленджа и теряете ставку. Поблажек и частичных штрафов нет.</li>
                <li>Мы <span class="font-medium">не присылаем напоминаний</span>: заведите свой будильник.</li>
            </ul>
        </x-card>

        <x-card title="Деньги">
            <ul class="list-disc pl-5 space-y-2">
                <li>С каждого пополнения удерживается комиссия {{ $percent }}%. Больше мы ничего не берём: выплаты за челлендж идут без комиссии.</li>
                <li>Ставку задаёт создатель группы: от <x-money :amount="$stakeMin" /> до <x-money :amount="$stakeMax" />. Чтобы хватило на ставку после комиссии, на странице группы мы показываем, на сколько нужно пополнить баланс.</li>
                <li>Ставки всех, кто пропустил, образуют общий фонд. Он делится поровну между теми, кто дошёл до конца.</li>
                <li>Если никто не пропустил — все получают ставки назад целиком.</li>
                <li>Остаток от деления на целые копейки остаётся у платформы.</li>
                <li>Баланс остаётся на платформе для следующего челленджа. Вывести деньги можно заявкой в разделе «Кошелёк», когда вы не участвуете в челлендже.</li>
            </ul>
        </x-card>

        <x-card title="Пример расчёта (комиссия {{ $percent }}%)">
            <dl class="grid grid-cols-[1fr_auto] gap-x-6 gap-y-2 text-sm">
                <dt>Участников в челлендже</dt><dd class="text-right tabular-nums">{{ $example['participants'] }}</dd>
                <dt>Каждый пополнил баланс на</dt><dd class="text-right"><x-money :amount="$example['top_up']" /></dd>
                <dt>Комиссия с каждого</dt><dd class="text-right"><x-money :amount="$example['commission']" /></dd>
                <dt>Ставка каждого</dt><dd class="text-right"><x-money :amount="$example['stake']" /></dd>
                <dt>Пропустили день</dt><dd class="text-right tabular-nums">{{ $example['eliminated'] }}</dd>
                <dt>Фонд потерянных ставок</dt><dd class="text-right"><x-money :amount="$example['pool']" /></dd>
                <dt>Дошли до конца</dt><dd class="text-right tabular-nums">{{ $example['survivors'] }}</dd>
                <dt>Доля фонда на каждого</dt><dd class="text-right"><x-money :amount="$example['share']" /></dd>
                <dt class="font-semibold text-gray-900">Каждый, кто дошёл до конца, получает</dt>
                <dd class="text-right font-semibold text-gray-900"><x-money :amount="$example['payout']" /></dd>
            </dl>
        </x-card>

        <x-card title="Частые вопросы" id="faq">
            <dl class="space-y-4">
                <div>
                    <dt class="font-medium text-gray-900">Могу ли я участвовать в нескольких челленджах сразу?</dt>
                    <dd>Пока нет: одновременно можно участвовать только в одном челлендже. Новый — после завершения текущего.</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-900">Можно ли выйти из челленджа?</dt>
                    <dd>Да, пока идёт набор участников — ставка вернётся на баланс. После старта выйти нельзя: это считается пропуском.</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-900">Что если не наберётся {{ $minParticipants }} участников?</dt>
                    <dd>Челлендж отменяется, а все ставки возвращаются на баланс.</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-900">Платформа проверяет, что я помолился?</dt>
                    <dd>Нет. Код подтверждает, что вы проснулись и бодрствуете в нужное время. Сервис построен на доверии.</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-900">Что делать, если из-за сбоя сайта меня исключили?</dt>
                    <dd>Напишите нам через страницу <a href="{{ route('contact') }}" class="underline text-emerald-800">«Контакты»</a> — администратор проверит и при подтверждённом сбое вернёт вас в челлендж.</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-900">Могу ли я сменить город?</dt>
                    <dd>Да, в настройках — но только пока вы не участвуете в челлендже.</dd>
                </div>
            </dl>
        </x-card>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('groups.index') }}" class="px-4 py-2 rounded-md bg-emerald-700 text-white hover:bg-emerald-800">Найти группу</a>
            @guest
                <a href="{{ route('register') }}" class="px-4 py-2 rounded-md border border-gray-300 bg-white hover:bg-gray-50">Зарегистрироваться</a>
            @endguest
        </div>
    </div>
</x-app-layout>
