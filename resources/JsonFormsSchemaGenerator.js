

function generateSurveySchema(config) {
  const { items, cell } = config;
  
  // Build the nested schema recursively
  function buildLevel(items, isRowLevel = true) {
    if (!items || items.length === 0) {
      // Leaf level - return input definition
      return {
        type: "number",
        "x-input-config": {
          labelPosition: "before",
          label: cell.inputConfig?.suffix || "%",
          step: cell.inputConfig?.step || "0.1",
          showButtons: false
        }
      };
    }
    
    const currentItem = items[0];
    const remainingItems = items.slice(1);
    
    // Determine if this level should be rows or columns
    // Alternates based on depth
    const layout = isRowLevel ? "row" : "column";
    
    const properties = {};
    
    // If there are rows defined at this level, use them as keys
    if (currentItem.rows && currentItem.rows.length > 0) {
      currentItem.rows.forEach(row => {
        properties[row.name] = buildLevel(remainingItems, !isRowLevel);
      });
    }
    // If there are columns defined at this level, use them as keys
    else if (currentItem.columns && currentItem.columns.length > 0) {
      currentItem.columns.forEach(col => {
        properties[col.name] = buildLevel(remainingItems, !isRowLevel);
      });
    }
    // If this item has child items, process them
    else if (currentItem.items && currentItem.items.length > 0) {
      return buildLevel(currentItem.items, isRowLevel);
    }
    
    return {
      type: "object",
      layout: layout,
      properties: properties,
      required: Object.keys(properties)
    };
  }
  
  // Build the complete schema
  const properties = {};
  items.forEach(item => {
    if (item.rows && item.rows.length > 0) {
      item.rows.forEach(row => {
        properties[row.name] = buildLevel(item.items || [], false);
      });
    } else if (item.columns && item.columns.length > 0) {
      item.columns.forEach(col => {
        properties[col.name] = buildLevel(item.items || [], true);
      });
    } else if (item.items) {
      Object.assign(properties, buildLevel(item.items, true).properties);
    }
  });
  
  return {
    $schema: "https://json-schema.org/draft/2020-12/schema",
    title: "Generated Survey Schema",
    description: "Auto-generated from configuration",
    type: "object",
    "x-layout": "survey",
    definitions: {
      input: {
        type: cell.type || "number",
        "x-input-config": {
          labelPosition: "before",
          label: cell.inputConfig?.suffix || "%",
          step: cell.inputConfig?.step || "0.1",
          showButtons: false,
          ...cell.inputConfig
        }
      }
    },
    properties: properties,
    required: Object.keys(properties)
  };
}

// Usage
const config = {...}; // Your configuration object
const surveySchema = generateSurveySchema(config);


