import assert from 'node:assert/strict';
import { registerHooks } from 'node:module';
import { test } from 'node:test';

registerHooks({
    resolve(specifier, context, nextResolve) {
        if (specifier === '@hotwired/stimulus') {
            return nextResolve(new URL('../../resources/js/vendor/@hotwired--stimulus.js', import.meta.url).href, context);
        }

        return nextResolve(specifier, context);
    },
});

const { default: OtpController } = await import('../../resources/js/controllers/otp_controller.js');

function createOtp(code = '') {
    const form = new EventTarget();
    const controller = new OtpController({});

    controller.inputTargets = Array.from({ length: 6 }, (_, index) => ({
        value: code[index] ?? '',
        focus() {},
    }));
    controller.codeTarget = Object.assign(new EventTarget(), { value: '', form });
    controller.connect();

    return { controller, form };
}

function pressKey(controller, index, key) {
    controller.handleKeydown({
        target: controller.inputTargets[index],
        key,
        preventDefault() {},
        stopPropagation() {},
    });
}

function submitCode(controller, form) {
    let code;
    form.addEventListener('submit', () => code = controller.codeTarget.value, { once: true });
    form.dispatchEvent(new Event('submit', { cancelable: true }));

    return code;
}

test('the final keyboard digit is ready for immediate submission', () => {
    const { controller } = createOtp('12345');

    pressKey(controller, 5, '6');

    assert.equal(controller.codeTarget.value, '123456');
});

test('submitting the owning form synchronizes the submitted code', () => {
    const { controller, form } = createOtp('12345');
    controller.inputTargets[5].value = '6';

    assert.equal(submitCode(controller, form), '123456');
});

for (const [code, index, expected] of [['123456', 5, '12345'], ['12345', 5, '1234']]) {
    test(`backspace from ${code} immediately submits ${expected}`, () => {
        const { controller, form } = createOtp(code);

        pressKey(controller, index, 'Backspace');

        assert.equal(controller.codeTarget.value, expected);
        assert.equal(submitCode(controller, form), expected);
    });
}

test('pasted digits are ready for immediate submission', () => {
    const { controller, form } = createOtp();

    controller.handlePaste({
        preventDefault() {},
        clipboardData: { getData: () => '654321' },
    });

    assert.equal(controller.codeTarget.value, '654321');
    assert.equal(submitCode(controller, form), '654321');
});

test('input-only mobile changes are sanitized and submitted immediately', () => {
    const { controller, form } = createOtp('12345');
    const target = controller.inputTargets[5];
    target.value = 'a67';

    controller.handleInput({ target });

    assert.equal(target.value, '6');
    assert.equal(controller.codeTarget.value, '123456');
    assert.equal(submitCode(controller, form), '123456');
});

test('input-only deletion is ready for immediate submission', () => {
    const { controller, form } = createOtp('123456');
    const target = controller.inputTargets[5];
    target.value = '';

    controller.handleInput({ target });

    assert.equal(controller.codeTarget.value, '12345');
    assert.equal(submitCode(controller, form), '12345');
});

test('disconnect removes the form listener and reconnect restores it', () => {
    const { controller, form } = createOtp('12345');
    controller.disconnect();
    controller.inputTargets[5].value = '6';

    assert.equal(submitCode(controller, form), '12345');

    controller.connect();
    controller.inputTargets[5].value = '7';

    assert.equal(submitCode(controller, form), '123457');
});

test('submitting another form does not change this code', () => {
    const { controller } = createOtp('12345');
    const otherForm = new EventTarget();
    controller.inputTargets[5].value = '6';

    otherForm.dispatchEvent(new Event('submit'));

    assert.equal(controller.codeTarget.value, '12345');
});
