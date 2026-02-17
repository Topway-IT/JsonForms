/**
 * This file is part of the MediaWiki extension JsonForms.
 *
 * JsonForms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * JsonForms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JsonForms. If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2026, https://wikisphere.org
 */

function JsonFormsUtilities() {}

JsonFormsUtilities.prototype.isObject = function (item) {
	return item && typeof item === 'object' && !Array.isArray(item);
};

JsonFormsUtilities.prototype.isEmptyObj = function (item) {
	return this.isObject(item) || !Object.keys(item).length;
};

JsonFormsUtilities.prototype.ucfirst = function (str) {
	if (typeof str !== 'string' || !str) return str;
	return str.charAt(0).toUpperCase() + str.slice(1);
};

// @credits https://medium.com/javascript-inside/safely-accessing-deeply-nested-values-in-javascript-99bf72a0855a
JsonFormsUtilities.prototype.getNestedProp = function (path, obj) {
	return path.reduce((xs, x) => (xs && xs[x] ? xs[x] : null), obj);
};

JsonFormsUtilities.prototype.removeArrayItem = function (arr, value) {
	const index = arr.indexOf(value);
	if (index !== -1) {
		arr.splice(index, 1);
	}
};

JsonFormsUtilities.prototype.uniqueID = function () {
	return Math.random().toString(16).slice(2);
};

const JFUtilities = new JsonFormsUtilities();

