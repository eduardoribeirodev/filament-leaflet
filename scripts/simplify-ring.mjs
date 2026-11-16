function distanceToSegment(point, first, last) {
    let x = first[0], y = first[1];
    const dx = last[0] - x, dy = last[1] - y;
    if (dx || dy) {
        const ratio = ((point[0] - x) * dx + (point[1] - y) * dy) / (dx * dx + dy * dy);
        if (ratio > 1) { x = last[0]; y = last[1]; }
        else if (ratio > 0) { x += dx * ratio; y += dy * ratio; }
    }
    return (point[0] - x) ** 2 + (point[1] - y) ** 2;
}

export function simplifyRing(ring, tolerance) {
    if (ring.length <= 4) return ring;
    const squared = tolerance * tolerance;
    const keep = new Set([0, ring.length - 1]);
    const pending = [[0, ring.length - 1]];
    while (pending.length) {
        const [first, last] = pending.pop();
        let max = squared, index = -1;
        for (let i = first + 1; i < last; i++) {
            const distance = distanceToSegment(ring[i], ring[first], ring[last]);
            if (distance > max) { max = distance; index = i; }
        }
        if (index === -1) continue;
        keep.add(index);
        if (index > first + 1) pending.push([first, index]);
        if (index < last - 1) pending.push([index, last]);
    }
    const result = [...keep].sort((a, b) => a - b).map((index) => ring[index]);
    return result.length >= 4 ? result : ring;
}
