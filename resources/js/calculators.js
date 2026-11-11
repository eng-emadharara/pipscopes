// Trader calculators (resources/views/tools/*): lot size, pip value, profit/loss. Everything runs in the
// browser from the form's inputs; a form is [data-calc="lot|pip|profit"] and its outputs are [data-out="…"].

// Pip size and the value of one pip for one standard lot, in US dollars. Pairs quoted in another
// currency (USDJPY, USDCHF, USDCAD) convert at the pair's own price, asked for in [data-price-field].
export const INSTRUMENTS = {
    XAUUSD: { pip: 0.1, value: () => 10 },          // 100 oz × 0.1
    XAGUSD: { pip: 0.01, value: () => 50 },         // 5 000 oz × 0.01
    EURUSD: { pip: 0.0001, value: () => 10 },
    GBPUSD: { pip: 0.0001, value: () => 10 },
    AUDUSD: { pip: 0.0001, value: () => 10 },
    NZDUSD: { pip: 0.0001, value: () => 10 },
    USDJPY: { pip: 0.01, value: (price) => 1000 / price, needsPrice: 150 },
    USDCHF: { pip: 0.0001, value: (price) => 10 / price, needsPrice: 0.88 },
    USDCAD: { pip: 0.0001, value: (price) => 10 / price, needsPrice: 1.38 },
    BTCUSD: { pip: 1, value: () => 1 },             // 1 BTC × $1
};

// Usual stop sizes (in pips) by instrument and trading style, as a starting point the trader can change
const TYPICAL_STOPS = {
    XAUUSD: { scalp: 30, day: 80, swing: 250 },
    XAGUSD: { scalp: 20, day: 40, swing: 120 },
    BTCUSD: { scalp: 300, day: 1000, swing: 3000 },
    USDJPY: { scalp: 12, day: 30, swing: 90 },
    default: { scalp: 10, day: 25, swing: 80 },
};

// Risk per trade, most open at once (total), and when to stop for the day / week: all % of the balance
export const PROFILES = {
    careful: { risk: 0.5, open: 2, day: 2, week: 4 },
    balanced: { risk: 1, open: 3, day: 3, week: 6 },
    bold: { risk: 2, open: 6, day: 5, week: 10 },
};

const money = (n) => (Number.isFinite(n) ? n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—');
const number = (n, digits = 1) => (Number.isFinite(n) ? n.toLocaleString('en-US', { maximumFractionDigits: digits }) : '—');
const read = (form, name) => parseFloat(String(form.elements[name]?.value ?? '').replace(',', '.'));

function pipValue(form, symbol, priceOverride) {
    const instrument = INSTRUMENTS[symbol];
    const price = priceOverride ?? read(form, 'price');
    return instrument.value(instrument.needsPrice ? price : undefined);
}

const calculators = {
    // A whole money management plan from the balance, the instrument, the style and how much risk the trader accepts
    plan(form, out) {
        const symbol = form.elements.symbol.value;
        const balance = read(form, 'balance');
        const profile = PROFILES[form.elements.profile.value] ?? PROFILES.balanced;
        const stop = read(form, 'stop');
        const perPip = pipValue(form, symbol);
        const riskAmount = balance * profile.risk / 100;
        const exact = riskAmount / (stop * perPip);
        const lots = Math.floor(exact * 100 + 1e-9) / 100;
        const tooSmall = lots < 0.01;
        // At the smallest lot brokers allow, what share of the balance one stop costs
        const minLotRisk = (0.01 * stop * perPip) / balance * 100;
        const afterStreak = balance * (1 - profile.risk / 100) ** 10;

        out('risk-pct', profile.risk + '%');
        out('risk-amount', '$' + money(riskAmount));
        out('lots', tooSmall ? '0.01' : number(lots, 2));
        out('stop-pips', number(stop, 0));
        out('stop-amount', '$' + money((tooSmall ? 0.01 : lots) * stop * perPip));
        out('target-pips', number(stop * 2, 0));
        out('target-amount', '+$' + money((tooSmall ? 0.01 : lots) * stop * 2 * perPip));
        out('open-trades', String(Math.max(1, Math.floor(profile.open / profile.risk))));
        out('day-limit', '$' + money(balance * profile.day / 100) + ' (' + profile.day + '%)');
        out('week-limit', '$' + money(balance * profile.week / 100) + ' (' + profile.week + '%)');
        out('streak-left', '$' + money(afterStreak));
        out('streak-loss', number((1 - afterStreak / balance) * 100, 1) + '%');
        out('min-lot-risk', number(minLotRisk, 1) + '%');

        // Too little for this stop: the smallest lot already risks more than planned
        form.querySelector('[data-plan-too-small]')?.toggleAttribute('hidden', !tooSmall);
    },

    lot(form, out) {
        const symbol = form.elements.symbol.value;
        const balance = read(form, 'balance');
        const risk = read(form, 'risk');
        const stop = read(form, 'stop');
        const perPip = pipValue(form, symbol);
        const riskAmount = balance * risk / 100;
        // Rounded down to the 0.01 brokers accept, so the risk never goes over what was asked
        // (the tiny epsilon keeps 2.9999999… from a float division at 0.03, not 0.02)
        const lots = Math.floor(riskAmount / (stop * perPip) * 100 + 1e-9) / 100;

        out('risk-amount', '$' + money(riskAmount));
        out('lots', number(lots, 2));
        out('per-pip', '$' + money(lots * perPip));
    },

    pip(form, out) {
        const lots = read(form, 'lots');
        const perPip = pipValue(form, form.elements.symbol.value) * lots;

        out('per-pip', '$' + money(perPip));
        out('per-10', '$' + money(perPip * 10));
        out('per-100', '$' + money(perPip * 100));
    },

    profit(form, out) {
        const symbol = form.elements.symbol.value;
        const instrument = INSTRUMENTS[symbol];
        const entry = read(form, 'entry');
        const exit = read(form, 'exit');
        const lots = read(form, 'lots');
        const direction = form.elements.direction.value === 'sell' ? -1 : 1;
        const pips = (exit - entry) / instrument.pip * direction;
        // Quoted in another currency: converted at the closing price
        const result = pips * pipValue(form, symbol, exit) * lots;

        out('pips', number(pips, 1));
        out('result', (result >= 0 ? '+$' : '−$') + money(Math.abs(result)));
        form.querySelector('[data-out="result"]')?.classList.toggle('text-red-600', result < 0);
        form.querySelector('[data-out="result"]')?.classList.toggle('text-brand-700', result >= 0);
    },
};

document.querySelectorAll('form[data-calc]').forEach((form) => {
    const calculate = calculators[form.dataset.calc];
    if (!calculate) return;

    const out = (name, value) => form.querySelectorAll(`[data-out="${name}"]`).forEach((el) => { el.textContent = value; });

    const update = () => {
        // The price field only for pairs that need it
        const instrument = INSTRUMENTS[form.elements.symbol.value];
        const priceField = form.querySelector('[data-price-field]');
        if (priceField) {
            priceField.hidden = !instrument.needsPrice;
            if (instrument.needsPrice && !form.elements.price.value) form.elements.price.value = instrument.needsPrice;
        }
        form.querySelectorAll('[data-pip-size]').forEach((el) => { el.textContent = instrument.pip; });
        calculate(form, out);
    };

    // The plan suggests a stop for the instrument and style, until the trader types their own
    const suggestStop = () => {
        const stopField = form.elements.stop;
        if (!stopField || !form.elements.style || stopField.dataset.touched) return;
        const stops = TYPICAL_STOPS[form.elements.symbol.value] ?? TYPICAL_STOPS.default;
        stopField.value = stops[form.elements.style.value] ?? stops.day;
    };

    form.addEventListener('input', (e) => {
        if (e.target.name === 'stop') e.target.dataset.touched = '1';
        update();
    });
    form.addEventListener('change', (e) => {
        // A new pair: its own typical price
        if (e.target.name === 'symbol' && form.elements.price) form.elements.price.value = INSTRUMENTS[e.target.value].needsPrice ?? '';
        // A new instrument or style: the usual stop for it (unless the trader set one)
        if (e.target.name === 'symbol' || e.target.name === 'style') suggestStop();
        update();
    });
    suggestStop();
    form.addEventListener('submit', (e) => { e.preventDefault(); update(); });
    update();
});
