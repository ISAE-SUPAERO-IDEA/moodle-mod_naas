export default {
  truncate(text, length, suffix) {
    suffix = suffix || "...";
    if (text && text.length > length) {
      return text.substring(0, length) + suffix;
    } else {
      return text;
    }
  },
};
