// Puts the dashes in a Dutch license plate while it is typed: "70GXL2" becomes "70-GXL-2".
// The 14 formats the RDW knows differ in where the letters (L) and digits (D) are, so the letters and digits that were typed
// tell where the dashes go. A dash appears as soon as all formats that fit what was typed agree on where it goes: often after three characters.
const LAYOUTS = {
    LLDDDD: [2, 2, 2],
    DDDDLL: [2, 2, 2],
    DDLLDD: [2, 2, 2],
    LLDDLL: [2, 2, 2],
    LLLLDD: [2, 2, 2],
    DDLLLL: [2, 2, 2],
    DDLLLD: [2, 3, 1],
    DLLLDD: [1, 3, 2],
    LLDDDL: [2, 3, 1],
    LDDDLL: [1, 3, 2],
    LLLDDL: [3, 2, 1],
    LDDLLL: [1, 2, 3],
    DLLDDD: [1, 2, 3],
    DDDLLD: [3, 2, 1],
};

export function formatPlate(typed) {
    const plate = String(typed).toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);

    // one pass: replacing digits by "D" first would make the next replace take that D for a letter
    const shape = plate.replace(/[0-9A-Z]/g, (character) => (/[0-9]/.test(character) ? 'D' : 'L'));

    // The formats that can still be meant by what was typed so far. With three characters there is often only one answer
    // for the first dash ("AB1" is always "AB-1", "1AB" always "1-AB"); "ABC" and "123" are not clear yet.
    const candidates = Object.entries(LAYOUTS).filter(([format]) => format.startsWith(shape)).map(([, lengths]) => lengths);

    if (plate.length === 0 || candidates.length === 0) {
        return plate;
    }

    // a dash goes behind position `boundary` when every candidate has it there and there is a character after it
    const boundaries = [candidates.map((lengths) => lengths[0]), candidates.map((lengths) => lengths[0] + lengths[1])];
    const dashes = boundaries
        .filter((positions) => positions.every((position) => position === positions[0]) && plate.length > positions[0])
        .map((positions) => positions[0]);

    let result = '';
    for (let i = 0; i < plate.length; i += 1) {
        if (dashes.includes(i)) {
            result += '-';
        }
        result += plate[i];
    }

    return result;
}

// Where the cursor goes after the dashes were added: behind the same number of letters and digits as before.
// Without this the cursor jumps to the end when someone corrects a character in the middle.
export function caretAfterFormat(before, caret, after) {
    const alnumBefore = String(before).slice(0, caret).replace(/[^A-Za-z0-9]/g, '').length;
    let seen = 0;

    for (let i = 0; i < after.length; i += 1) {
        if (seen === alnumBefore) {
            return i;
        }
        if (/[A-Z0-9]/.test(after[i])) {
            seen += 1;
        }
    }

    return after.length;
}

// Every field marked data-plate-input gets the dashes as you type or paste.
export function attachPlateFormatting() {
    document.querySelectorAll('[data-plate-input]').forEach((input) => {
        input.addEventListener('input', () => {
            const before = input.value;
            const caret = input.selectionStart ?? before.length;
            const after = formatPlate(before);

            if (after === before) {
                return;
            }

            input.value = after;

            // keep the cursor where it was (only when it was not at the end, where it belongs anyway)
            if (caret < before.length && typeof input.setSelectionRange === 'function') {
                const position = caretAfterFormat(before, caret, after);
                input.setSelectionRange(position, position);
            }
        });
    });
}
