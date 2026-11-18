import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

type Shape =
  | { tag: 'path'; d: string }
  | { tag: 'circle'; cx: number; cy: number; r: number }
  | { tag: 'rect'; x: number; y: number; width: number; height: number; rx: number };

/** Icônes au trait (24×24, style « outline ») inspirées de Lucide (licence ISC). */
const ICONS = {
  search: [
    { tag: 'circle', cx: 11, cy: 11, r: 8 },
    { tag: 'path', d: 'm21 21-4.3-4.3' },
  ],
  heart: [
    {
      tag: 'path',
      d: 'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z',
    },
  ],
  message: [{ tag: 'path', d: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z' }],
  user: [
    { tag: 'path', d: 'M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2' },
    { tag: 'circle', cx: 12, cy: 7, r: 4 },
  ],
  plus: [
    { tag: 'path', d: 'M5 12h14' },
    { tag: 'path', d: 'M12 5v14' },
  ],
  pin: [
    { tag: 'path', d: 'M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z' },
    { tag: 'circle', cx: 12, cy: 10, r: 3 },
  ],
  locate: [
    { tag: 'circle', cx: 12, cy: 12, r: 3 },
    { tag: 'circle', cx: 12, cy: 12, r: 8 },
    { tag: 'path', d: 'M12 2v2M12 20v2M2 12h2M20 12h2' },
  ],
  chevronDown: [{ tag: 'path', d: 'm6 9 6 6 6-6' }],
  chevronRight: [{ tag: 'path', d: 'm9 18 6-6-6-6' }],
  arrowLeft: [
    { tag: 'path', d: 'm12 19-7-7 7-7' },
    { tag: 'path', d: 'M19 12H5' },
  ],
  check: [{ tag: 'path', d: 'M20 6 9 17l-5-5' }],
  logout: [
    { tag: 'path', d: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4' },
    { tag: 'path', d: 'm16 17 5-5-5-5' },
    { tag: 'path', d: 'M21 12H9' },
  ],
  shield: [
    { tag: 'path', d: 'M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z' },
    { tag: 'path', d: 'm9 12 2 2 4-4' },
  ],
  truck: [
    { tag: 'path', d: 'M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2' },
    { tag: 'path', d: 'M15 18H9' },
    { tag: 'path', d: 'M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14' },
    { tag: 'circle', cx: 17, cy: 18, r: 2 },
    { tag: 'circle', cx: 7, cy: 18, r: 2 },
  ],
  sparkles: [
    { tag: 'path', d: 'M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z' },
    { tag: 'path', d: 'M19 3v4M21 5h-4' },
  ],
  tag: [
    { tag: 'path', d: 'M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4z' },
    { tag: 'circle', cx: 7.5, cy: 7.5, r: 1.5 },
  ],
  crop: [
    { tag: 'path', d: 'M6 2v14a2 2 0 0 0 2 2h14' },
    { tag: 'path', d: 'M18 22V8a2 2 0 0 0-2-2H2' },
  ],
  trash: [
    { tag: 'path', d: 'M3 6h18' },
    { tag: 'path', d: 'M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6' },
    { tag: 'path', d: 'M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2' },
    { tag: 'path', d: 'M10 11v6M14 11v6' },
  ],
  camera: [
    { tag: 'path', d: 'M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z' },
    { tag: 'circle', cx: 12, cy: 13, r: 3 },
  ],
  // Catégories
  car: [
    { tag: 'path', d: 'M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2' },
    { tag: 'circle', cx: 7, cy: 17, r: 2 },
    { tag: 'path', d: 'M9 17h6' },
    { tag: 'circle', cx: 17, cy: 17, r: 2 },
  ],
  house: [
    { tag: 'path', d: 'M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8' },
    { tag: 'path', d: 'M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z' },
  ],
  shirt: [
    { tag: 'path', d: 'M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z' },
  ],
  sofa: [
    { tag: 'path', d: 'M20 9V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v3' },
    { tag: 'path', d: 'M2 16a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v1.5a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5V11a2 2 0 0 0-4 0z' },
    { tag: 'path', d: 'M4 18v2M20 18v2M12 4v9' },
  ],
  smartphone: [
    { tag: 'rect', x: 5, y: 2, width: 14, height: 20, rx: 2 },
    { tag: 'path', d: 'M12 18h.01' },
  ],
  bike: [
    { tag: 'circle', cx: 18.5, cy: 17.5, r: 3.5 },
    { tag: 'circle', cx: 5.5, cy: 17.5, r: 3.5 },
    { tag: 'circle', cx: 15, cy: 5, r: 1 },
    { tag: 'path', d: 'M12 17.5V14l-3-3 4-3 2 3h2' },
  ],
  briefcase: [
    { tag: 'path', d: 'M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16' },
    { tag: 'rect', x: 2, y: 6, width: 20, height: 14, rx: 2 },
  ],
  wrench: [
    { tag: 'path', d: 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z' },
  ],
  factory: [
    { tag: 'path', d: 'M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z' },
    { tag: 'path', d: 'M17 18h1M12 18h1M7 18h1' },
  ],
  grid: [
    { tag: 'rect', x: 3, y: 3, width: 7, height: 7, rx: 1 },
    { tag: 'rect', x: 14, y: 3, width: 7, height: 7, rx: 1 },
    { tag: 'rect', x: 14, y: 14, width: 7, height: 7, rx: 1 },
    { tag: 'rect', x: 3, y: 14, width: 7, height: 7, rx: 1 },
  ],
} satisfies Record<string, Shape[]>;

export type IconName = keyof typeof ICONS;

/** Icône décorative par défaut (aria-hidden) ; fournir `label` si elle porte seule le sens. */
@Component({
  selector: 'app-icon',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'icon' },
  template: `
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      [attr.width]="size()"
      [attr.height]="size()"
      fill="none"
      stroke="currentColor"
      [attr.stroke-width]="stroke()"
      stroke-linecap="round"
      stroke-linejoin="round"
      [attr.aria-hidden]="label() ? null : 'true'"
      [attr.role]="label() ? 'img' : null"
      [attr.aria-label]="label()"
      focusable="false"
    >
      @for (shape of shapes(); track $index) {
        @switch (shape.tag) {
          @case ('path') {
            <path [attr.d]="$any(shape).d" />
          }
          @case ('circle') {
            <circle [attr.cx]="$any(shape).cx" [attr.cy]="$any(shape).cy" [attr.r]="$any(shape).r" />
          }
          @case ('rect') {
            <rect
              [attr.x]="$any(shape).x"
              [attr.y]="$any(shape).y"
              [attr.width]="$any(shape).width"
              [attr.height]="$any(shape).height"
              [attr.rx]="$any(shape).rx"
            />
          }
        }
      }
    </svg>
  `,
})
export class Icon {
  readonly name = input.required<IconName>();
  readonly size = input(20);
  readonly stroke = input(1.75);
  readonly label = input<string | null>(null);

  protected readonly shapes = computed<Shape[]>(() => ICONS[this.name()]);
}

/** Icône associée à une catégorie racine (par slug), avec repli générique. */
export function categoryIcon(slug: string): IconName {
  const map: Record<string, IconName> = {
    vehicules: 'car',
    immobilier: 'house',
    mode: 'shirt',
    'maison-jardin': 'sofa',
    multimedia: 'smartphone',
    loisirs: 'bike',
    emploi: 'briefcase',
    services: 'wrench',
    'materiel-professionnel': 'factory',
  };
  return map[slug] ?? 'grid';
}
