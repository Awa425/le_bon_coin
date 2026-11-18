export type AttributeType = 'text' | 'number' | 'boolean' | 'select' | 'multi_select';

export interface AttributeDefinition {
  key: string;
  label: string;
  type: AttributeType;
  unit: string | null;
  options: string[];
  min: number | null;
  max: number | null;
  required: boolean;
  filterable: boolean;
}

export interface CategoryNode {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  children?: CategoryNode[];
}

export interface CategoryDetail extends CategoryNode {
  is_leaf: boolean;
  breadcrumb: Pick<CategoryNode, 'id' | 'name' | 'slug'>[];
  attributes: AttributeDefinition[];
}
