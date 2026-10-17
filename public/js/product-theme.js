(function() {
  const article = document.querySelector(".product-detail");
  if (!article) return;
  const productTheme = (article.getAttribute("data-product-theme") || "").toLowerCase().trim();
  const productCategory = (article.getAttribute("data-product-category") || "").toLowerCase().trim();
  const productName = (article.getAttribute("data-product-name") || "").toLowerCase().trim();
  function inferTheme() {
    if (productTheme) return productTheme;
    if (productName.includes("matcha")) return "matcha";
    if (productName.includes("caramel") || productName.includes("macchiato")) return "caramel";
    if (productName.includes("spanish latte") || productName.includes("spanish-latte")) return "spanish-latte";
    if (productName.includes("mocha") || productName.includes("chocolate")) return "mocha";
    if (productName.includes("strawberry") || productName.includes("rose") || productName.includes("pink")) return "strawberry";
    if (productName.includes("latte")) return "spanish-latte";
    if (productName.includes("coffee") || productName.includes("americano")) return "spanish-latte";
    if (productName.includes("cocoa")) return "mocha";
    if (productCategory.includes("drink") || productCategory.includes("beverage")) {
      if (productCategory.includes("matcha")) return "matcha";
      if (productCategory.includes("caramel")) return "caramel";
      if (productCategory.includes("strawberry") || productCategory.includes("berry")) return "strawberry";
      if (productCategory.includes("mocha") || productCategory.includes("chocolate")) return "mocha";
      return "spanish-latte";
    }
    if (productCategory.includes("food") || productCategory.includes("meal") || productCategory.includes("pastry") || productCategory.includes("snack")) {
      return "food";
    }
    return "";
  }
  const theme = inferTheme();
  if (theme) {
    article.setAttribute("data-theme", theme);
    article.classList.add("product-detail--themed");
  }
})();
