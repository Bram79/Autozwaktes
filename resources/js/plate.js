// Puts the dashes in a Dutch license plate while it is typed: "70GXL2" becomes "70-GXL-2".
// The 14 formats the RDW knows differ in where the letters (L) and digits (D) are, so the letters and digits that were typed
// tell where the dashes go. Until six characters are typed there is no way to know, so the text stays as it is.
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

    if (plate.length < 6) {
        return plate;
    }

    // one pass: replacing digits by "D" first would make the next replace take that D for a letter
    const shape = plate.replace(/[0-9A-Z]/g, (character) => (/[0-9]/.test(character) ? 'D' : 'L'));
    const lengths = LAYOUTS[shape];

    if (! lengths) {
        return plate;
    }

    const parts = [];
    let start = 0;
    lengths.forEach((length) => {
        parts.push(plate.slice(start, start + length));
        start += length;
    });

    return parts.join('-');
}

// Every field marked data-plate-input gets the dashes as you type or paste.
export function attachPlateFormatting() {
    document.querySelectorAll('[data-plate-input]').forEach((input) => {
        input.addEventListener('input', () => {
            input.value = formatPlate(input.value);
        });
    });
}
